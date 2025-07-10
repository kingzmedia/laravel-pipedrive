<?php

namespace Keggermont\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;
use Keggermont\LaravelPipedrive\Traits\EmitsPipedriveEvents;
use Devio\Pipedrive\Pipedrive;
use Keggermont\LaravelPipedrive\Models\PipedriveActivity;
use Keggermont\LaravelPipedrive\Models\PipedriveDeal;
use Keggermont\LaravelPipedrive\Models\PipedriveFile;
use Keggermont\LaravelPipedrive\Models\PipedriveGoal;
use Keggermont\LaravelPipedrive\Models\PipedriveNote;
use Keggermont\LaravelPipedrive\Models\PipedriveOrganization;
use Keggermont\LaravelPipedrive\Models\PipedrivePerson;
use Keggermont\LaravelPipedrive\Models\PipedrivePipeline;
use Keggermont\LaravelPipedrive\Models\PipedriveProduct;
use Keggermont\LaravelPipedrive\Models\PipedriveStage;
use Keggermont\LaravelPipedrive\Models\PipedriveUser;

class SyncPipedriveEntitiesCommand extends Command
{
    use EmitsPipedriveEvents;
    public $signature = 'pipedrive:sync-entities
                        {--entity= : Sync specific entity (activities, deals, files, goals, notes, organizations, persons, pipelines, products, stages, users)}
                        {--limit=500 : Limit number of records to sync per entity (max 500)}
                        {--full-data : Retrieve ALL data with pagination (sorted by creation date, oldest first). WARNING: Use with caution due to API rate limits}
                        {--force : Force sync even if records already exist, and skip confirmation prompts for --full-data}';

    public $description = 'Synchronize entities from Pipedrive API. By default, fetches latest modifications (sorted by update_time DESC, max 500 records). Use --full-data to retrieve all data with pagination.';

    protected Pipedrive $pipedrive;
    protected PipedriveAuthService $authService;

    /**
     * Available entities and their model classes
     */
    protected array $entities = [
        'activities' => PipedriveActivity::class,
        'deals' => PipedriveDeal::class,
        'files' => PipedriveFile::class,
        'goals' => PipedriveGoal::class,
        'notes' => PipedriveNote::class,
        'organizations' => PipedriveOrganization::class,
        'persons' => PipedrivePerson::class,
        'pipelines' => PipedrivePipeline::class,
        'products' => PipedriveProduct::class,
        'stages' => PipedriveStage::class,
        'users' => PipedriveUser::class,
    ];

    public function __construct(PipedriveAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    public function handle(): int
    {
        $this->info('Starting Pipedrive entities synchronization...');

        // Initialize Pipedrive client
        try {
            $this->pipedrive = $this->authService->getPipedriveInstance();
            
            // Test connection
            $connectionTest = $this->authService->testConnection();
            if (!$connectionTest['success']) {
                $this->error('Failed to connect to Pipedrive: ' . $connectionTest['message']);
                return self::FAILURE;
            }
            
            $this->info('Connected to Pipedrive as: ' . $connectionTest['user'] . ' (' . $connectionTest['company'] . ')');
            $this->info('Using authentication method: ' . $this->authService->getAuthMethod());
            
        } catch (\Exception $e) {
            $this->error('Error initializing Pipedrive client: ' . $e->getMessage());
            return self::FAILURE;
        }

        $entityType = $this->option('entity');
        $limit = min((int) $this->option('limit'), 500); // Enforce API limit
        $fullData = $this->option('full-data');
        $force = $this->option('force');

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

        if ($entityType) {
            if (!array_key_exists($entityType, $this->entities)) {
                $this->error("Invalid entity type. Available types: " . implode(', ', array_keys($this->entities)));
                return self::FAILURE;
            }

            $this->syncEntity($entityType, $limit, $force, $fullData);
        } else {
            // Sync all entities
            foreach (array_keys($this->entities) as $entity) {
                $this->syncEntity($entity, $limit, $force, $fullData);
            }
        }

        $this->info('Entities synchronization completed!');
        return self::SUCCESS;
    }

    protected function syncEntity(string $entityType, int $limit, bool $force = false, bool $fullData = false): void
    {
        $this->info("Syncing {$entityType}...");

        if ($fullData) {
            $this->line("  → Full data mode: Retrieving ALL data with pagination (sorted by creation date, oldest first)");
        } else {
            $this->line("  → Standard mode: Retrieving latest modifications (sorted by update_time DESC, max {$limit} records)");
        }

        try {
            $modelClass = $this->entities[$entityType];
            $this->line("  → Fetching data from Pipedrive API...");

            if ($this->getOutput()->isVerbose()) {
                $this->line("  → Debug: About to call getDataFromPipedrive with fullData=" . ($fullData ? 'true' : 'false'));
            }

            $data = $this->getDataFromPipedrive($entityType, $limit, $fullData);

            if ($this->getOutput()->isVerbose()) {
                $this->line("  → Debug: Returned from getDataFromPipedrive, data type: " . gettype($data));
                $this->line("  → Debug: Data count: " . (is_countable($data) ? count($data) : 'not countable'));
            }

            if (empty($data)) {
                $this->warn("No data found for entity type: {$entityType}");
                return;
            }

            $this->line("  → Found " . count($data) . " records to process");

            // Debug: Show data structure in verbose mode
            if ($this->getOutput()->isVerbose()) {
                $this->line("  → Debug: Data type: " . gettype($data));
                if (is_array($data) && !empty($data)) {
                    $this->line("  → Debug: First item type: " . gettype($data[0] ?? 'N/A'));
                    $this->line("  → Debug: First item keys: " . implode(', ', array_keys($data[0] ?? [])));
                } elseif (is_object($data)) {
                    $this->line("  → Debug: Object class: " . get_class($data));
                }
            }

            $synced = 0;
            $updated = 0;
            $skipped = 0;
            $errors = 0;

            // Convert objects to arrays if needed
            if (is_object($data)) {
                $data = json_decode(json_encode($data), true);
            }

            foreach ($data as $index => $itemData) {
                $this->line("  → Processing item " . ($index + 1) . "/" . count($data));

                // Convert individual item data to array if it's an object
                if (is_object($itemData)) {
                    $itemData = json_decode(json_encode($itemData), true);
                }

                // Skip items without an ID
                if (!isset($itemData['id']) || $itemData['id'] === null) {
                    $this->warn("  ⚠ Skipped item without ID in {$entityType}");
                    $skipped++;
                    continue;
                }

                $existingRecord = $modelClass::where('pipedrive_id', $itemData['id'])->first();

                if ($existingRecord && !$force) {
                    $skipped++;
                    continue;
                }

                try {
                    $this->line("  → Creating/updating record with ID: {$itemData['id']}");

                    $record = $modelClass::createOrUpdateFromPipedriveData($itemData);

                    if ($record->wasRecentlyCreated) {
                        $synced++;
                        $this->line("  ✓ Created: {$this->getRecordDisplayName($record, $itemData)}");

                        // Emit created event
                        $this->emitModelCreated($record, $itemData, 'sync', [
                            'command' => 'sync-entities',
                            'entity_type' => $entityType,
                            'force' => $force,
                        ]);
                    } else {
                        $updated++;
                        $this->line("  ↻ Updated: {$this->getRecordDisplayName($record, $itemData)}");

                        // Emit updated event
                        $changes = $this->extractModelChanges($record);
                        $this->emitModelUpdated($record, $itemData, $changes, 'sync', [
                            'command' => 'sync-entities',
                            'entity_type' => $entityType,
                            'force' => $force,
                        ]);
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("  ✗ Error processing {$entityType} item {$itemData['id']}: " . $e->getMessage());
                    $this->error("  ✗ Stack trace: " . $e->getTraceAsString());
                    continue;
                }
            }

            $this->info("  {$entityType}: {$synced} created, {$updated} updated, {$skipped} skipped, {$errors} errors");

        } catch (\Exception $e) {
            $this->error("Error syncing {$entityType}: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
        }
    }

    protected function getDataFromPipedrive(string $entityType, int $limit, bool $fullData = false): array
    {
        if ($this->getOutput()->isVerbose()) {
            $this->line("  → Debug: getDataFromPipedrive called with entityType={$entityType}, limit={$limit}, fullData=" . ($fullData ? 'true' : 'false'));
        }

        try {
            if ($fullData) {
                if ($this->getOutput()->isVerbose()) {
                    $this->line("  → Debug: Calling getAllDataWithPagination");
                }
                $result = $this->getAllDataWithPagination($entityType, $limit);
                if ($this->getOutput()->isVerbose()) {
                    $this->line("  → Debug: getAllDataWithPagination returned " . count($result) . " items");
                }
                return $result;
            } else {
                if ($this->getOutput()->isVerbose()) {
                    $this->line("  → Debug: Calling getLatestModifications");
                }
                $result = $this->getLatestModifications($entityType, $limit);
                if ($this->getOutput()->isVerbose()) {
                    $this->line("  → Debug: getLatestModifications returned " . count($result) . " items");
                }
                return $result;
            }
        } catch (\Exception $e) {
            $this->error("Failed to fetch {$entityType} from Pipedrive: " . $e->getMessage());
            if ($this->getOutput()->isVerbose()) {
                $this->error("Stack trace: " . $e->getTraceAsString());
            }
            return [];
        }
    }

    /**
     * Get latest modifications (default mode)
     * Sorted by update_time DESC (most recent first)
     */
    protected function getLatestModifications(string $entityType, int $limit): array
    {
        $options = [
            'limit' => $limit,
            'sort' => 'update_time DESC', // Most recent modifications first
        ];

        $response = $this->makeApiCall($entityType, $options);

        if (!$response->isSuccess()) {
            throw new \Exception("API request failed with status: " . $response->getStatusCode());
        }

        $data = $response->getData() ?? [];

        // Convert objects to arrays if needed
        if (is_array($data)) {
            $data = array_map(function($item) {
                return is_object($item) ? json_decode(json_encode($item), true) : $item;
            }, $data);
        }

        return $data;
    }

    /**
     * Get all data with pagination (full-data mode)
     * Sorted by add_time ASC (oldest first)
     */
    protected function getAllDataWithPagination(string $entityType, int $limit): array
    {
        $allData = [];
        $start = 0;
        $hasMore = true;
        $pageCount = 0;

        $options = [
            'limit' => $limit,
            'sort' => 'add_time ASC', // Oldest first for consistent pagination
        ];

        while ($hasMore) {
            $pageCount++;
            $options['start'] = $start;

            if ($this->getOutput()->isVerbose()) {
                $this->line("    → Fetching page {$pageCount} (start: {$start}, limit: {$limit})");
            }

            try {
                $response = $this->makeApiCall($entityType, $options);

                if (!$response->isSuccess()) {
                    throw new \Exception("API request failed with status: " . $response->getStatusCode() . " on page {$pageCount}");
                }
            } catch (\Exception $e) {
                $this->error("    ✗ Error on page {$pageCount}: " . $e->getMessage());
                if ($this->getOutput()->isVerbose()) {
                    $this->error("    ✗ Stack trace: " . $e->getTraceAsString());
                }
                break; // Stop pagination on error
            }

            $pageData = $response->getData() ?? [];

            // Convert objects to arrays if needed
            if (is_array($pageData)) {
                $pageData = array_map(function($item) {
                    return is_object($item) ? json_decode(json_encode($item), true) : $item;
                }, $pageData);
            }

            if ($this->getOutput()->isVerbose()) {
                $this->line("    → Debug: Response success: " . ($response->isSuccess() ? 'true' : 'false'));
                $this->line("    → Debug: Page data type: " . gettype($pageData));
                $this->line("    → Debug: Page data count: " . count($pageData));
                if (!empty($pageData) && is_array($pageData)) {
                    $this->line("    → Debug: First item in page has ID: " . ($pageData[0]['id'] ?? 'NO ID'));
                }
            }

            $allData = array_merge($allData, $pageData);

            // Check if we have more data
            $hasMore = count($pageData) === $limit;
            $start += $limit;

            if ($this->getOutput()->isVerbose()) {
                $this->line("    → Page {$pageCount}: " . count($pageData) . " records (total: " . count($allData) . ")");
            }

            // Safety check to prevent infinite loops and memory issues
            if ($pageCount > 20) {
                $this->warn("    ⚠ Reached maximum page limit (20) for safety. Stopping pagination.");
                $this->warn("    ⚠ Use standard mode for large datasets or increase memory limits.");
                break;
            }

            // Memory check
            $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
            $memoryLimit = ini_get('memory_limit');
            $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
            $memoryLimitMB = $memoryLimitBytes > 0 ? $memoryLimitBytes / 1024 / 1024 : -1;

            if ($this->getOutput()->isVerbose()) {
                $this->line("    → Memory usage: {$memoryUsage}MB" . ($memoryLimitMB > 0 ? " / {$memoryLimitMB}MB" : " (unlimited)"));
            }

            // Stop if we're using more than 80% of available memory
            if ($memoryLimitMB > 0 && $memoryUsage > ($memoryLimitMB * 0.8)) {
                $this->error("    ❌ MEMORY LIMIT REACHED: Using {$memoryUsage}MB of {$memoryLimitMB}MB limit (80% threshold).");
                $this->error("    💡 Increase memory limit: php -d memory_limit=2048M artisan ...");
                throw new \Exception("Memory limit reached during pagination. Increase PHP memory_limit to continue.");
            }

            // Warning at 60% usage
            if ($memoryLimitMB > 0 && $memoryUsage > ($memoryLimitMB * 0.6)) {
                $this->warn("    ⚠ Memory usage high: {$memoryUsage}MB of {$memoryLimitMB}MB (60%+)");
            }
        }

        if ($this->getOutput()->isVerbose()) {
            $this->line("    → Pagination completed: {$pageCount} pages, " . count($allData) . " total records");
            $this->line("    → Debug: Final allData type: " . gettype($allData));
            $this->line("    → Debug: Final allData count: " . count($allData));
            if (!empty($allData)) {
                $this->line("    → Debug: Final first item type: " . gettype($allData[0] ?? 'N/A'));
                $this->line("    → Debug: Final first item has ID: " . ($allData[0]['id'] ?? 'NO ID'));
            }
        }

        return $allData;
    }

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

            if (!$this->confirm('Continue anyway? (may cause out-of-memory errors)', false)) {
                $this->info('Operation cancelled. Use one of the solutions above.');
                exit(self::FAILURE);
            }
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
     * Make API call for specific entity type
     */
    protected function makeApiCall(string $entityType, array $options)
    {
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
