<?php

namespace Keggermont\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ScheduledSyncPipedriveCommand extends Command
{
    public $signature = 'pipedrive:scheduled-sync
                        {--dry-run : Show what would be synced without actually running the sync}
                        {--verbose : Enable verbose output}';

    public $description = 'Run scheduled full synchronization of Pipedrive entities and custom fields based on configuration';

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
        $fullData = $config['full_data'] ?? true;
        $force = $config['force'] ?? true;
        $syncCustomFields = $config['sync_custom_fields'] ?? true;
        $memoryLimit = $config['memory_limit'] ?? 2048;

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

            // Sync entities
            $this->info('🔄 Synchronizing entities...');
            $exitCode = $this->syncEntities($force, $fullData, $isVerbose);
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

    protected function displaySyncConfiguration(bool $fullData, bool $force, bool $syncCustomFields, int $memoryLimit): void
    {
        $this->line('📋 Sync Configuration:');
        $this->line('  • Full data mode: ' . ($fullData ? '✅ Enabled' : '❌ Disabled'));
        $this->line('  • Force mode: ' . ($force ? '✅ Enabled' : '❌ Disabled'));
        $this->line('  • Sync custom fields: ' . ($syncCustomFields ? '✅ Enabled' : '❌ Disabled'));
        $this->line('  • Memory limit: ' . ($memoryLimit > 0 ? $memoryLimit . 'MB' : 'No limit'));
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
