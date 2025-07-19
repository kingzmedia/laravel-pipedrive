<?php

namespace Skeylup\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Skeylup\LaravelPipedrive\Jobs\SyncPipedriveEntityJob;
use Skeylup\LaravelPipedrive\Data\SyncOptions;
use Skeylup\LaravelPipedrive\Services\PipedriveEntityConfigService;

class ScheduledSyncPipedriveCommand extends Command
{
    public $signature = 'pipedrive:scheduled-sync
                        {--dry-run : Show what would be synced without actually running the sync}
                        {--verbose : Enable verbose output}';

    public $description = 'Run scheduled synchronization of Pipedrive entities and custom fields using robust job system (always uses standard mode with limit=500 for safety)';

    protected PipedriveEntityConfigService $entityConfigService;

    public function __construct(PipedriveEntityConfigService $entityConfigService)
    {
        parent::__construct();
        $this->entityConfigService = $entityConfigService;
    }

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isVerbose = $this->option('verbose');

        // Check if scheduler is enabled
        if (!Config::get('pipedrive.sync.scheduler.enabled', false)) {
            $this->warn('Scheduled sync is disabled in configuration. Enable it by setting PIPEDRIVE_SCHEDULER_ENABLED=true');
            return self::SUCCESS;
        }

        $this->info('🚀 Starting scheduled Pipedrive synchronization...');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual synchronization will be performed');
        }

        // Get configuration
        $config = Config::get('pipedrive.sync.scheduler', []);
        // IMPORTANT: Scheduler NEVER uses full-data mode for safety and performance
        $fullData = false; // Always false for scheduled operations
        $force = $config['force'] ?? true;
        $syncCustomFields = $config['sync_custom_fields'] ?? true;
        $limit = $config['limit'] ?? 500; // Standard limit for scheduled sync

        // Set memory limit if specified
        if ($memoryLimit > 0) {
            $currentLimit = ini_get('memory_limit');
            $newLimit = $memoryLimit . 'M';
            ini_set('memory_limit', $newLimit);
            
            if ($isVerbose) {
                $this->line("📊 Memory limit changed from {$currentLimit} to {$newLimit}");
            }
        }

        $this->displaySyncConfiguration($fullData, $force, $syncCustomFields, $memoryLimit);

        if ($isDryRun) {
            $this->info('✅ Dry run completed - configuration validated');
            return self::SUCCESS;
        }

        $startTime = microtime(true);
        $totalErrors = 0;

        try {
            // Sync custom fields first if enabled
            if ($syncCustomFields) {
                $this->info('📋 Synchronizing custom fields...');
                $exitCode = $this->syncCustomFields($force, $fullData, $isVerbose);
                if ($exitCode !== 0) {
                    $totalErrors++;
                    $this->error('❌ Custom fields sync failed');
                } else {
                    $this->info('✅ Custom fields sync completed');
                }
            }

            // Sync entities using robust job system
            $this->info('🔄 Synchronizing entities using robust job system...');
            $exitCode = $this->syncEntitiesUsingJobs($force, $limit, $isVerbose);
            if ($exitCode !== 0) {
                $totalErrors++;
                $this->error('❌ Entities sync failed');
            } else {
                $this->info('✅ Entities sync completed');
            }

        } catch (\Exception $e) {
            $this->error('💥 Scheduled sync failed with exception: ' . $e->getMessage());
            
            if ($isVerbose) {
                $this->error('Stack trace: ' . $e->getTraceAsString());
            }

            // Log the error
            Log::error('Pipedrive scheduled sync failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'config' => $config,
            ]);

            return self::FAILURE;
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        if ($totalErrors > 0) {
            $this->error("⚠️  Scheduled sync completed with {$totalErrors} error(s) in {$duration} seconds");
            return self::FAILURE;
        }

        $this->info("🎉 Scheduled sync completed successfully in {$duration} seconds");
        
        // Log successful completion
        Log::info('Pipedrive scheduled sync completed successfully', [
            'duration' => $duration,
            'config' => $config,
        ]);

        return self::SUCCESS;
    }

    protected function displaySyncConfiguration(bool $fullData, bool $force, bool $syncCustomFields, int $limit): void
    {
        $this->line('📋 Sync Configuration:');
        $this->line('  • Full data mode: ' . ($fullData ? '✅ Enabled' : '❌ Disabled (SAFE - scheduler always uses standard mode)'));
        $this->line('  • Force mode: ' . ($force ? '✅ Enabled' : '❌ Disabled'));
        $this->line('  • Sync custom fields: ' . ($syncCustomFields ? '✅ Enabled' : '❌ Disabled'));
        $this->line('  • Record limit: ' . $limit . ' (sorted by last modified)');
        $this->line('  • Robustness: ✅ Enabled (rate limiting, error handling, memory management)');
        $this->line('');
    }

    protected function syncCustomFields(bool $force, bool $fullData, bool $verbose): int
    {
        $command = 'pipedrive:sync-custom-fields';
        $arguments = [];

        if ($fullData) {
            $arguments['--full-data'] = true;
        }

        if ($force) {
            $arguments['--force'] = true;
        }

        if ($verbose) {
            $arguments['--verbose'] = true;
        }

        return Artisan::call($command, $arguments);
    }

    /**
     * Sync entities using the robust job system
     * IMPORTANT: Always uses standard mode (not full-data) for safety
     */
    protected function syncEntitiesUsingJobs(bool $force, int $limit, bool $verbose): int
    {
        $entities = $this->entityConfigService->getEnabledEntities();

        if (empty($entities)) {
            $this->warn('⚠️  No entities are enabled for synchronization.');
            $this->warn('   Configure PIPEDRIVE_ENABLED_ENTITIES environment variable to enable entities.');
            return self::SUCCESS;
        }

        if ($verbose) {
            $configSummary = $this->entityConfigService->getConfigurationSummary();
            $this->line('📋 Scheduler Entity Configuration:');
            $this->line("  → Enabled entities: " . implode(', ', $configSummary['enabled_entities']));
            if (!empty($configSummary['disabled_entities'])) {
                $this->line("  → Disabled entities: " . implode(', ', $configSummary['disabled_entities']));
            }
        }

        $totalErrors = 0;
        $totalSuccess = 0;

        foreach ($entities as $entityType) {
            try {
                $this->line("  🔄 Syncing {$entityType}...");

                // Create sync options for scheduler execution
                // IMPORTANT: fullData is ALWAYS false for scheduled operations
                $options = SyncOptions::forScheduler(
                    $entityType,
                    $force
                );

                // Override limit if specified
                $options = $options->withChanges(['limit' => $limit]);

                // Execute job synchronously for scheduler
                $result = SyncPipedriveEntityJob::executeSync($options);

                if ($result->isSuccess()) {
                    $totalSuccess++;
                    $this->line("    ✅ {$entityType}: {$result->synced} created, {$result->updated} updated, {$result->skipped} skipped");

                    if ($verbose && $result->errors > 0) {
                        $this->line("    ⚠️  {$result->errors} errors occurred");
                    }
                } else {
                    $totalErrors++;
                    $this->error("    ❌ {$entityType} sync failed: " . ($result->errorMessage ?? 'Unknown error'));
                }

            } catch (\Exception $e) {
                $totalErrors++;
                $this->error("    ❌ {$entityType} sync failed with exception: " . $e->getMessage());

                if ($verbose) {
                    $this->line("    → Exception details: " . $e->getTraceAsString());
                }
            }
        }

        $this->line('');
        $this->info("📊 Entities sync summary: {$totalSuccess} successful, {$totalErrors} failed");

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function syncEntities(bool $force, bool $fullData, bool $verbose): int
    {
        $command = 'pipedrive:sync-entities';
        $arguments = [];

        if ($fullData) {
            $arguments['--full-data'] = true;
        }

        if ($force) {
            $arguments['--force'] = true;
        }

        if ($verbose) {
            $arguments['--verbose'] = true;
        }

        return Artisan::call($command, $arguments);
    }
}
