<?php

namespace Keggermont\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;
use Keggermont\LaravelPipedrive\Traits\EmitsPipedriveEvents;
use Keggermont\LaravelPipedrive\Jobs\SyncPipedriveEntityJob;
use Keggermont\LaravelPipedrive\Data\SyncOptions;
use Keggermont\LaravelPipedrive\Data\SyncResult;
use Keggermont\LaravelPipedrive\Services\PipedriveMemoryManager;
use Devio\Pipedrive\Pipedrive;

class SyncPipedriveEntitiesCommand extends Command
{
    use EmitsPipedriveEvents;

    public $signature = 'pipedrive:sync-entities
                        {--entity= : Sync specific entity (activities, deals, files, goals, notes, organizations, persons, pipelines, products, stages, users)}
                        {--limit=500 : Limit number of records to sync per entity (max 500)}
                        {--full-data : Retrieve ALL data with pagination (sorted by creation date, oldest first). WARNING: Use with caution due to API rate limits}
                        {--force : Force sync even if records already exist, and skip confirmation prompts for --full-data}';

    public $description = 'Synchronize entities from Pipedrive API using the robust centralized job system. By default, fetches latest modifications (sorted by update_time DESC, max 500 records). Use --full-data to retrieve all data with pagination.';

    protected PipedriveAuthService $authService;
    protected PipedriveMemoryManager $memoryManager;

    /**
     * Available entities
     */
    protected array $entities = [
        'activities', 'deals', 'files', 'goals', 'notes', 'organizations',
        'persons', 'pipelines', 'products', 'stages', 'users'
    ];

    public function __construct(
        PipedriveAuthService $authService,
        PipedriveMemoryManager $memoryManager
    ) {
        parent::__construct();
        $this->authService = $authService;
        $this->memoryManager = $memoryManager;
    }

    public function handle(): int
    {
        $this->info('🚀 Starting Pipedrive entities synchronization using robust job system...');

        // Parse command options
        $entityType = $this->option('entity');
        $limit = min((int) $this->option('limit'), 500); // Enforce API limit
        $fullData = $this->option('full-data');
        $force = $this->option('force');
        $verbose = $this->option('verbose');

        // Warning for full-data mode
        if ($fullData) {
            $this->warn('⚠️  WARNING: Full data mode enabled. This will retrieve ALL data with pagination.');
            $this->warn('⚠️  This may take a long time and consume significant API rate limits.');
            $this->warn('⚠️  Use this option with caution, especially in production environments.');

            // Check memory limit
            $this->checkMemoryRequirements();

            if (!$force && !$this->confirm('Do you want to continue?', false)) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }

            if ($force) {
                $this->info('🚀 Force mode enabled - skipping confirmation prompt.');
            }
        }

        // Test connection first
        try {
            $connectionTest = $this->authService->testConnection();
            if (!$connectionTest['success']) {
                $this->error('❌ Failed to connect to Pipedrive: ' . $connectionTest['message']);
                return self::FAILURE;
            }

            $this->info('✅ Connected to Pipedrive as: ' . $connectionTest['user'] . ' (' . $connectionTest['company'] . ')');
            $this->info('🔐 Using authentication method: ' . $this->authService->getAuthMethod());

        } catch (\Exception $e) {
            $this->error('❌ Error testing Pipedrive connection: ' . $e->getMessage());
            return self::FAILURE;
        }

        // Validate entity type
        if ($entityType) {
            if (!in_array($entityType, $this->entities)) {
                $this->error("❌ Invalid entity type. Available types: " . implode(', ', $this->entities));
                return self::FAILURE;
            }

            return $this->syncEntityUsingJob($entityType, $limit, $force, $fullData, $verbose);
        } else {
            // Sync all entities
            $totalResults = [];
            foreach ($this->entities as $entity) {
                $result = $this->syncEntityUsingJob($entity, $limit, $force, $fullData, $verbose);
                if ($result === self::FAILURE) {
                    $this->error("❌ Failed to sync {$entity}, stopping execution.");
                    return self::FAILURE;
                }
                $totalResults[] = $entity;
            }

            $this->info('✅ All entities synchronization completed! Synced: ' . implode(', ', $totalResults));
            return self::SUCCESS;
        }
    }

    /**
     * Sync entity using the centralized job system
     */
    protected function syncEntityUsingJob(
        string $entityType,
        int $limit,
        bool $force = false,
        bool $fullData = false,
        bool $verbose = false
    ): int {
        $this->info("🔄 Syncing {$entityType}...");

        if ($fullData) {
            $this->line("  → Full data mode: Retrieving ALL data with pagination (sorted by creation date, oldest first)");
        } else {
            $this->line("  → Standard mode: Retrieving latest modifications (sorted by update_time DESC, max {$limit} records)");
        }

        try {
            // Create sync options for command execution
            $options = SyncOptions::forCommand(
                $entityType,
                $limit,
                $fullData,
                $force,
                $verbose
            );

            if ($verbose) {
                $this->line("  → Options: " . json_encode($options->toArray(), JSON_PRETTY_PRINT));
                $this->line("  → Memory stats before sync: " . json_encode($this->memoryManager->getMemoryStats(), JSON_PRETTY_PRINT));
            }

            // Execute job synchronously for command
            $result = SyncPipedriveEntityJob::executeSync($options);

            // Display results
            $this->displaySyncResult($entityType, $result, $verbose);

            if ($result->isSuccess()) {
                return self::SUCCESS;
            } else {
                $this->error("❌ Sync failed for {$entityType}: " . ($result->errorMessage ?? 'Unknown error'));
                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error syncing {$entityType}: " . $e->getMessage());

            if ($verbose) {
                $this->line("  → Exception details: " . $e->getTraceAsString());
            }

            Log::error("Error syncing {$entityType} via job", [
                'entity_type' => $entityType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'options' => [
                    'limit' => $limit,
                    'force' => $force,
                    'full_data' => $fullData,
                    'verbose' => $verbose,
                ],
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Display sync result in a user-friendly format
     */
    protected function displaySyncResult(string $entityType, SyncResult $result, bool $verbose = false): void
    {
        $summary = $result->getSummary();

        if ($result->isSuccess()) {
            $this->info("  ✅ {$entityType} sync completed successfully!");
        } else {
            $this->error("  ❌ {$entityType} sync failed!");
        }

        // Display statistics
        $this->line("  📊 Results:");
        $this->line("    • Synced: {$summary['totals']['synced']}");
        $this->line("    • Updated: {$summary['totals']['updated']}");
        $this->line("    • Skipped: {$summary['totals']['skipped']}");
        $this->line("    • Errors: {$summary['totals']['errors']}");
        $this->line("    • Total processed: {$summary['totals']['total_processed']}");
        $this->line("    • Execution time: {$summary['timing']['formatted_execution_time']}");

        if ($summary['totals']['total_processed'] > 0) {
            $this->line("    • Success rate: " . number_format($summary['rates']['success_rate'], 1) . "%");
            $this->line("    • Processing speed: " . number_format($summary['rates']['processing_speed'], 1) . " items/sec");
        }

        // Show detailed information in verbose mode
        if ($verbose) {
            $detailedReport = $result->getDetailedReport();

            if (!empty($detailedReport['memory_stats'])) {
                $this->line("  🧠 Memory stats:");
                $memStats = $detailedReport['memory_stats'];
                $this->line("    • Usage: {$memStats['memory_used_formatted']} / {$memStats['memory_limit_formatted']} ({$memStats['usage_percent']}%)");
                $this->line("    • Batch size: {$memStats['current_batch_size']}");
            }

            if (!empty($detailedReport['rate_limit_stats'])) {
                $this->line("  🚦 Rate limit stats:");
                $rateStats = $detailedReport['rate_limit_stats'];
                $this->line("    • Usage: {$rateStats['current_usage']}/{$rateStats['daily_budget']} tokens ({$rateStats['usage_percentage']}%)");
                $this->line("    • Remaining: {$rateStats['remaining_tokens']} tokens");
            }

            if (!empty($detailedReport['error_items'])) {
                $this->line("  ⚠️  Error items:");
                foreach (array_slice($detailedReport['error_items'], 0, 5) as $errorItem) {
                    $this->line("    • ID {$errorItem['id']}: {$errorItem['error']}");
                }
                if (count($detailedReport['error_items']) > 5) {
                    $remaining = count($detailedReport['error_items']) - 5;
                    $this->line("    • ... and {$remaining} more errors");
                }
            }
        }

        $this->newLine();
    }

    /**
     * Check memory requirements for full-data operations
     */


    /**
     * Check memory requirements for full-data mode
     */
    protected function checkMemoryRequirements(): void
    {
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        $currentUsage = memory_get_usage(true);
        $currentUsageMB = round($currentUsage / 1024 / 1024, 2);

        // Recommend at least 1GB for full-data mode
        $recommendedBytes = 1024 * 1024 * 1024; // 1GB

        $this->info("💾 Memory Check:");
        $this->info("   Current limit: {$memoryLimit}");
        $this->info("   Current usage: {$currentUsageMB}MB");
        $this->info("   Recommended for full-data: 1GB+");

        if ($memoryLimitBytes !== -1 && $memoryLimitBytes < $recommendedBytes) {
            $this->error('❌ MEMORY WARNING: Your PHP memory limit may be insufficient for full-data mode.');
            $this->error('   Current limit: ' . $memoryLimit);
            $this->error('   Recommended: 1GB or higher');
            $this->error('');
            $this->error('💡 Solutions:');
            $this->error('   1. Increase PHP memory limit: php -d memory_limit=2048M artisan ...');
            $this->error('   2. Use standard mode instead (remove --full-data flag)');
            $this->error('   3. Sync specific entities with smaller datasets');
            $this->error('   4. Update php.ini: memory_limit = 2048M');
            $this->error('');
 
        } else {
            $this->info("✅ Memory limit appears sufficient for full-data mode.");
        }
    }

    /**
     * Parse memory limit string to bytes
     */
    protected function parseMemoryLimit(string $memoryLimit): int
    {
        if ($memoryLimit === '-1') {
            return -1; // Unlimited
        }

        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $value = (int) $memoryLimit;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Apply API delay to prevent rate limiting
     */
    protected function applyApiDelay(): void
    {
        $delayEnabled = Config::get('pipedrive.sync.api.delay_enabled', true);
        $delay = Config::get('pipedrive.sync.api.delay', 0.3);

        if ($delayEnabled && $delay > 0) {
            if ($this->getOutput()->isVerbose()) {
                $this->line("  → Applying API delay: {$delay}s");
            }

            // Convert to microseconds for usleep
            usleep((int)($delay * 1000000));
        }
    }

    /**
     * Make API call for specific entity type with rate limiting
     */
    protected function makeApiCall(string $entityType, array $options)
    {
        // Apply delay before API call to prevent rate limiting
        $this->applyApiDelay();

        if ($this->getOutput()->isVerbose()) {
            $this->line("  → Making API call for {$entityType} with options: " . json_encode($options));
        }

        return match ($entityType) {
            'activities' => $this->pipedrive->activities->all($options),
            'deals' => $this->pipedrive->deals->all($options),
            'files' => $this->pipedrive->files->all($options),
            'goals' => $this->pipedrive->goals->all($options),
            'notes' => $this->pipedrive->notes->all($options),
            'organizations' => $this->pipedrive->organizations->all($options),
            'persons' => $this->pipedrive->persons->all($options),
            'pipelines' => $this->pipedrive->pipelines->all($options),
            'products' => $this->pipedrive->products->all($options),
            'stages' => $this->pipedrive->stages->all($options),
            'users' => $this->pipedrive->users->all($options),
            default => throw new \InvalidArgumentException("Unsupported entity type: {$entityType}"),
        };
    }

    protected function getRecordDisplayName($record, array $data): string
    {
        // Try to get a meaningful display name for the record
        if (isset($data['title'])) {
            return $data['title'] . " (ID: {$data['id']})";
        }
        
        if (isset($data['name'])) {
            return $data['name'] . " (ID: {$data['id']})";
        }
        
        if (isset($data['subject'])) {
            return $data['subject'] . " (ID: {$data['id']})";
        }
        
        return "ID: {$data['id']}";
    }
}
