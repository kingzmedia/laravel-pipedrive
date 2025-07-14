<?php

namespace Keggermont\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Keggermont\LaravelPipedrive\Models\PipedriveCustomField;
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;
use Devio\Pipedrive\Pipedrive;

class SyncPipedriveCustomFieldsCommand extends Command
{
    public $signature = 'pipedrive:sync-custom-fields
                        {--entity= : Sync fields for specific entity (deal, person, organization, product, activity)}
                        {--full-data : Retrieve ALL fields with pagination (sorted by creation date, oldest first). WARNING: Use with caution due to API rate limits}
                        {--force : Force sync even if fields already exist, and skip confirmation prompts for --full-data}';

    public $description = 'Synchronize custom fields from Pipedrive API. By default, fetches latest modifications (sorted by update_time DESC). Use --full-data to retrieve all fields with pagination.';

    protected Pipedrive $pipedrive;
    protected PipedriveAuthService $authService;

    public function __construct(PipedriveAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    public function handle(): int
    {
        $this->info('Starting Pipedrive custom fields synchronization...');

        // Initialize Pipedrive client
        try {
            $this->pipedrive = $this->authService->getPipedriveInstance();

            // Test connection
            $connectionTest = $this->authService->testConnection();
            if (!$connectionTest['success']) {
                $this->error('Failed to connect to Pipedrive: ' . $connectionTest['message']);
                return self::FAILURE;
            }

            if ($this->getOutput()->isVerbose()) {
                $this->info('Connected to Pipedrive as: ' . $connectionTest['user'] . ' (' . $connectionTest['company'] . ')');
                $this->info('Using authentication method: ' . $this->authService->getAuthMethod());
            }

        } catch (\Exception $e) {
            $this->error('Error initializing Pipedrive client: ' . $e->getMessage());
            return self::FAILURE;
        }

        $entityType = $this->option('entity');
        $fullData = $this->option('full-data');
        $force = $this->option('force');

        // Warning for full-data mode
        if ($fullData) {
            $this->warn('⚠️  WARNING: Full data mode enabled. This will retrieve ALL custom fields with pagination.');
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
            if (!in_array($entityType, PipedriveCustomField::getEntityTypes())) {
                $this->error("Invalid entity type. Available types: " . implode(', ', PipedriveCustomField::getEntityTypes()));
                return self::FAILURE;
            }

            $this->syncEntityFields($entityType, $force, $fullData);
        } else {
            // Sync all entity types
            foreach (PipedriveCustomField::getEntityTypes() as $entity) {
                $this->syncEntityFields($entity, $force, $fullData);
            }
        }

        $this->info('Custom fields synchronization completed!');
        return self::SUCCESS;
    }

    protected function syncEntityFields(string $entityType, bool $force = false, bool $fullData = false): void
    {
        $this->info("Syncing {$entityType} fields...");

        if ($fullData) {
            $this->line("  → Full data mode: Retrieving ALL custom fields with pagination (sorted by creation date, oldest first)");
        } else {
            $this->line("  → Standard mode: Retrieving latest modifications (sorted by update_time DESC)");
        }

        try {
            $fields = $this->getFieldsFromPipedrive($entityType, $fullData);

            if (empty($fields)) {
                $this->warn("No fields found for entity type: {$entityType}");
                return;
            }

            $synced = 0;
            $updated = 0;
            $skipped = 0;

            // Convert objects to arrays if needed
            if (is_object($fields)) {
                $fields = json_decode(json_encode($fields), true);
            }

            foreach ($fields as $fieldData) {
                // Convert individual field data to array if it's an object
                if (is_object($fieldData)) {
                    $fieldData = json_decode(json_encode($fieldData), true);
                }

                // Skip fields without an ID (system/primary fields)
                if (!isset($fieldData['id']) || $fieldData['id'] === null) {
                    if ($this->getOutput()->isVerbose()) {
                        $this->warn("  ⚠ Skipped system field: {$fieldData['name']} ({$fieldData['key']})");
                    }
                    $skipped++;
                    continue;
                }

                $existingField = PipedriveCustomField::where('pipedrive_id', $fieldData['id'])
                    ->where('entity_type', $entityType)
                    ->first();

                if ($existingField && !$force) {
                    $skipped++;
                    continue;
                }

                try {
                    $field = PipedriveCustomField::createOrUpdateFromPipedriveData($fieldData, $entityType);

                    if ($field->wasRecentlyCreated) {
                        $synced++;
                        if ($this->getOutput()->isVerbose()) {
                            $this->line("  ✓ Created: {$field->name} ({$field->key})");
                        }
                    } else {
                        $updated++;
                        if ($this->getOutput()->isVerbose()) {
                            $this->line("  ↻ Updated: {$field->name} ({$field->key})");
                        }
                    }
                } catch (\Exception $e) {
                    $this->error("  ✗ Error processing field {$fieldData['name']}: " . $e->getMessage());
                    if ($this->getOutput()->isVerbose()) {
                        $this->error("    Stack trace: " . $e->getTraceAsString());
                    }
                    continue;
                }
            }

            $this->info("  {$entityType}: {$synced} created, {$updated} updated, {$skipped} skipped");

        } catch (\Exception $e) {
            $this->error("Error syncing {$entityType} fields: " . $e->getMessage());
        }
    }

    protected function getFieldsFromPipedrive(string $entityType, bool $fullData = false): array
    {
        try {
            if ($fullData) {
                return $this->getAllFieldsWithPagination($entityType);
            } else {
                return $this->getLatestFieldModifications($entityType);
            }
        } catch (\Exception $e) {
            $this->error("Failed to fetch {$entityType} fields from Pipedrive: " . $e->getMessage());
            if ($this->getOutput()->isVerbose()) {
                $this->error("Stack trace: " . $e->getTraceAsString());
            }
            return [];
        }
    }

    /**
     * Get latest field modifications (default mode)
     * Sorted by update_time DESC (most recent first)
     */
    protected function getLatestFieldModifications(string $entityType): array
    {
        $options = [
            'limit' => 500, // Max limit for fields
            'sort' => 'update_time DESC', // Most recent modifications first
        ];

        $response = $this->makeFieldApiCall($entityType, $options);

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
     * Get all fields with pagination (full-data mode)
     * Sorted by add_time ASC (oldest first)
     */
    protected function getAllFieldsWithPagination(string $entityType): array
    {
        $allFields = [];
        $start = 0;
        $limit = 500; // Max limit for fields
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

            $response = $this->makeFieldApiCall($entityType, $options);

            if (!$response->isSuccess()) {
                throw new \Exception("API request failed with status: " . $response->getStatusCode() . " on page {$pageCount}");
            }

            $pageData = $response->getData() ?? [];

            // Convert objects to arrays if needed
            if (is_array($pageData)) {
                $pageData = array_map(function($item) {
                    return is_object($item) ? json_decode(json_encode($item), true) : $item;
                }, $pageData);
            }

            $allFields = array_merge($allFields, $pageData);

            // Check if we have more data
            $hasMore = count($pageData) === $limit;
            $start += $limit;

            if ($this->getOutput()->isVerbose()) {
                $this->line("    → Page {$pageCount}: " . count($pageData) . " fields (total: " . count($allFields) . ")");
            }

            // Safety check to prevent infinite loops
            if ($pageCount > 100) {
                $this->warn("    ⚠ Reached maximum page limit (100). Stopping pagination.");
                break;
            }
        }

        if ($this->getOutput()->isVerbose()) {
            $this->line("    → Pagination completed: {$pageCount} pages, " . count($allFields) . " total fields");
        }

        return $allFields;
    }



    /**
     * Make API call for specific field entity type with rate limiting
     */
    protected function makeFieldApiCall(string $entityType, array $options)
    {
        // Apply delay before API call to prevent rate limiting
        $this->applyApiDelay();

        if ($this->getOutput()->isVerbose()) {
            $this->line("  → Making API call for {$entityType} fields with options: " . json_encode($options));
        }

        return match ($entityType) {
            PipedriveCustomField::ENTITY_DEAL => $this->pipedrive->dealFields->all($options),
            PipedriveCustomField::ENTITY_PERSON => $this->pipedrive->personFields->all($options),
            PipedriveCustomField::ENTITY_ORGANIZATION => $this->pipedrive->organizationFields->all($options),
            PipedriveCustomField::ENTITY_PRODUCT => $this->pipedrive->productFields->all($options),
            PipedriveCustomField::ENTITY_ACTIVITY => $this->pipedrive->activityFields->all($options),
            PipedriveCustomField::ENTITY_NOTE => $this->pipedrive->noteFields->all($options),
            default => throw new \InvalidArgumentException("Unsupported entity type: {$entityType}"),
        };
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

        // Recommend at least 512MB for custom fields full-data mode
        $recommendedBytes = 512 * 1024 * 1024; // 512MB

        $this->info("💾 Memory Check:");
        $this->info("   Current limit: {$memoryLimit}");
        $this->info("   Current usage: {$currentUsageMB}MB");
        $this->info("   Recommended for full-data: 512MB+");

        if ($memoryLimitBytes !== -1 && $memoryLimitBytes < $recommendedBytes) {
            $this->error('❌ MEMORY WARNING: Your PHP memory limit may be insufficient for full-data mode.');
            $this->error('   Current limit: ' . $memoryLimit);
            $this->error('   Recommended: 512MB or higher');
            $this->error('');
            $this->error('💡 Solutions:');
            $this->error('   1. Increase PHP memory limit: php -d memory_limit=1024M artisan ...');
            $this->error('   2. Use standard mode instead (remove --full-data flag)');
            $this->error('   3. Update php.ini: memory_limit = 1024M');
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
}
