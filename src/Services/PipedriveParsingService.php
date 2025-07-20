<?php

namespace Skeylup\LaravelPipedrive\Services;

use Illuminate\Support\Facades\Log;
use Skeylup\LaravelPipedrive\Exceptions\PipedriveException;
use Skeylup\LaravelPipedrive\Traits\EmitsPipedriveEvents;

/**
 * Centralized parsing service for Pipedrive data
 *
 * Extracts common logic from commands and provides unified
 * data fetching, processing, and synchronization capabilities
 */
class PipedriveParsingService
{
    use EmitsPipedriveEvents;

    protected PipedriveAuthService $authService;

    protected PipedriveRateLimitManager $rateLimitManager;

    protected PipedriveErrorHandler $errorHandler;

    protected PipedriveMemoryManager $memoryManager;

    protected PipedriveHealthChecker $healthChecker;

    protected $pipedrive;

    protected array $entityModelMap = [
        'activities' => \Skeylup\LaravelPipedrive\Models\PipedriveActivity::class,
        'deals' => \Skeylup\LaravelPipedrive\Models\PipedriveDeal::class,
        'files' => \Skeylup\LaravelPipedrive\Models\PipedriveFile::class,
        'goals' => \Skeylup\LaravelPipedrive\Models\PipedriveGoal::class,
        'notes' => \Skeylup\LaravelPipedrive\Models\PipedriveNote::class,
        'organizations' => \Skeylup\LaravelPipedrive\Models\PipedriveOrganization::class,
        'persons' => \Skeylup\LaravelPipedrive\Models\PipedrivePerson::class,
        'pipelines' => \Skeylup\LaravelPipedrive\Models\PipedrivePipeline::class,
        'products' => \Skeylup\LaravelPipedrive\Models\PipedriveProduct::class,
        'stages' => \Skeylup\LaravelPipedrive\Models\PipedriveStage::class,
        'users' => \Skeylup\LaravelPipedrive\Models\PipedriveUser::class,
    ];

    public function __construct(
        PipedriveAuthService $authService,
        PipedriveRateLimitManager $rateLimitManager,
        PipedriveErrorHandler $errorHandler,
        PipedriveMemoryManager $memoryManager,
        PipedriveHealthChecker $healthChecker
    ) {
        $this->authService = $authService;
        $this->rateLimitManager = $rateLimitManager;
        $this->errorHandler = $errorHandler;
        $this->memoryManager = $memoryManager;
        $this->healthChecker = $healthChecker;
    }

    /**
     * Initialize Pipedrive client with health check
     */
    public function initializePipedriveClient(): array
    {
        try {
            $this->pipedrive = $this->authService->getPipedriveInstance();

            // Test connection
            $connectionTest = $this->authService->testConnection();
            if (! $connectionTest['success']) {
                throw new \Exception('Failed to connect to Pipedrive: '.$connectionTest['message']);
            }

            // Perform health check if enabled
            if ($this->healthChecker->shouldPerformHealthCheck()) {
                $healthStatus = $this->healthChecker->performHealthCheck();
                if (! $healthStatus['healthy']) {
                    Log::warning('Pipedrive API health check failed', $healthStatus);
                }
            }

            return [
                'success' => true,
                'user' => $connectionTest['user'],
                'company' => $connectionTest['company'],
                'auth_method' => $this->authService->getAuthMethod(),
            ];

        } catch (\Exception $e) {
            $classified = $this->errorHandler->classifyException($e, [
                'operation' => 'initialize_client',
            ]);

            throw $classified;
        }
    }

    /**
     * Fetch data from Pipedrive API with robustness features
     */
    public function fetchEntityData(string $entityType, array $options = []): array
    {
        $options = array_merge([
            'limit' => 500,
            'full_data' => false,
            'force' => false,
        ], $options);

        try {
            if ($options['full_data']) {
                return $this->getAllDataWithPagination($entityType, $options['limit']);
            } else {
                return $this->getLatestModifications($entityType, $options['limit']);
            }
        } catch (\Exception $e) {
            $classified = $this->errorHandler->classifyException($e, [
                'operation' => 'fetch_entity_data',
                'entity_type' => $entityType,
                'options' => $options,
            ]);

            throw $classified;
        }
    }

    /**
     * Process entity data with memory management and error handling
     */
    public function processEntityData(string $entityType, array $data, array $options = []): array
    {
        $options = array_merge([
            'force' => false,
            'context' => 'sync',
            'verbose' => false,
        ], $options);

        $modelClass = $this->entityModelMap[$entityType] ?? null;
        if (! $modelClass) {
            throw new \InvalidArgumentException("Unsupported entity type: {$entityType}");
        }

        $results = [
            'synced' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'processed_items' => [],
            'error_items' => [],
        ];

        foreach ($data as $index => $itemData) {
            // Monitor memory usage
            $this->memoryManager->monitorMemoryUsage("process_{$entityType}");
            $this->memoryManager->checkMemoryThreshold("process_{$entityType}", count($data));

            // Convert individual item data to array if it's an object
            if (is_object($itemData)) {
                $itemData = json_decode(json_encode($itemData), true);
            }

            // Skip items without an ID
            if (! isset($itemData['id']) || $itemData['id'] === null) {
                $results['skipped']++;

                continue;
            }

            $existingRecord = $modelClass::where('pipedrive_id', $itemData['id'])->first();

            if ($existingRecord && ! $options['force']) {
                $results['skipped']++;

                continue;
            }

            try {
                $record = $modelClass::createOrUpdateFromPipedriveData($itemData);

                if ($record->wasRecentlyCreated) {
                    $results['synced']++;
                    $results['processed_items'][] = [
                        'action' => 'created',
                        'id' => $itemData['id'],
                        'record' => $record,
                    ];

                    // Emit created event
                    $this->emitModelCreated($record, $itemData, $options['context'], [
                        'entity_type' => $entityType,
                        'force' => $options['force'],
                    ]);
                } else {
                    $results['updated']++;
                    $results['processed_items'][] = [
                        'action' => 'updated',
                        'id' => $itemData['id'],
                        'record' => $record,
                    ];

                    // Emit updated event
                    $changes = $this->extractModelChanges($record);
                    $this->emitModelUpdated($record, $itemData, $changes, $options['context'], [
                        'entity_type' => $entityType,
                        'force' => $options['force'],
                    ]);
                }

                // Force garbage collection periodically
                if (($index + 1) % 100 === 0) {
                    $this->memoryManager->forceGarbageCollection();
                }

            } catch (\Exception $e) {
                $results['errors']++;
                $results['error_items'][] = [
                    'id' => $itemData['id'],
                    'error' => $e->getMessage(),
                    'data' => $itemData,
                ];

                $classified = $this->errorHandler->classifyException($e, [
                    'operation' => 'process_item',
                    'entity_type' => $entityType,
                    'item_id' => $itemData['id'],
                ]);

                Log::error('Error processing entity item', [
                    'entity_type' => $entityType,
                    'item_id' => $itemData['id'],
                    'error' => $classified->getErrorInfo(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Get latest modifications (default mode)
     */
    protected function getLatestModifications(string $entityType, int $limit): array
    {
        $options = [
            'limit' => $limit,
            'sort' => 'update_time DESC',
        ];

        $response = $this->makeApiCallWithRetry($entityType, $options);
        $data = $response->getData() ?? [];

        return $this->normalizeDataArray($data);
    }

    /**
     * Get all data with pagination (full-data mode)
     */
    protected function getAllDataWithPagination(string $entityType, int $limit): array
    {
        $allData = [];
        $start = 0;
        $hasMore = true;
        $pageCount = 0;
        $batchSize = $this->memoryManager->getAdaptiveBatchSize();

        $options = [
            'limit' => min($limit, $batchSize),
            'sort' => 'add_time ASC',
        ];

        while ($hasMore) {
            $pageCount++;
            $options['start'] = $start;

            // Monitor memory and adjust batch size
            $this->memoryManager->monitorMemoryUsage("pagination_{$entityType}");
            $options['limit'] = $this->memoryManager->getAdaptiveBatchSize();

            try {
                $response = $this->makeApiCallWithRetry($entityType, $options);
                $pageData = $response->getData() ?? [];
                $pageData = $this->normalizeDataArray($pageData);

                $allData = array_merge($allData, $pageData);

                // Check if we have more data
                $hasMore = count($pageData) === $options['limit'];
                $start += $options['limit'];

                Log::debug('Pagination progress', [
                    'entity_type' => $entityType,
                    'page' => $pageCount,
                    'page_records' => count($pageData),
                    'total_records' => count($allData),
                    'memory_usage' => $this->memoryManager->getMemoryUsagePercent(),
                    'batch_size' => $options['limit'],
                ]);

                // Safety check to prevent infinite loops and memory issues
                if ($pageCount > 100) {
                    Log::warning('Reached maximum page limit for safety', [
                        'entity_type' => $entityType,
                        'pages' => $pageCount,
                        'total_records' => count($allData),
                    ]);
                    break;
                }

                // Force garbage collection every 10 pages
                if ($pageCount % 10 === 0) {
                    $this->memoryManager->forceGarbageCollection();
                }

            } catch (PipedriveException $e) {
                $this->errorHandler->recordFailure($e);

                if (! $this->errorHandler->shouldRetry($e, 1)) {
                    throw $e;
                }

                $delay = $this->errorHandler->getRetryDelay($e, 1);
                Log::warning('Pagination error, retrying', [
                    'entity_type' => $entityType,
                    'page' => $pageCount,
                    'error' => $e->getMessage(),
                    'retry_delay' => $delay,
                ]);

                sleep($delay);

                continue;
            }
        }

        return $allData;
    }

    /**
     * Make API call with retry logic and rate limiting
     */
    protected function makeApiCallWithRetry(string $entityType, array $options, int $maxRetries = 3)
    {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                // Check rate limits
                if (! $this->rateLimitManager->canMakeRequest($entityType)) {
                    throw $this->rateLimitManager->handleRateLimitResponse([], $entityType);
                }

                // Apply rate limiting delay
                $this->rateLimitManager->waitForRateLimit($attempt);

                // Make the API call
                $response = $this->makeApiCall($entityType, $options);

                // Consume tokens on success
                $this->rateLimitManager->consumeTokens($entityType);

                // Record success for circuit breaker
                $this->errorHandler->recordSuccess('api');

                return $response;

            } catch (PipedriveException $e) {
                $this->errorHandler->recordFailure($e);

                if (! $this->errorHandler->shouldRetry($e, $attempt)) {
                    throw $e;
                }

                $delay = $this->errorHandler->getRetryDelay($e, $attempt);
                Log::warning('API call failed, retrying', [
                    'entity_type' => $entityType,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage(),
                    'retry_delay' => $delay,
                ]);

                sleep($delay);
            }
        }

        throw new \Exception("Max retry attempts ({$maxRetries}) exceeded for {$entityType}");
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

    /**
     * Normalize data array (convert objects to arrays)
     */
    protected function normalizeDataArray($data): array
    {
        if (! is_array($data)) {
            return [];
        }

        return array_map(function ($item) {
            return is_object($item) ? json_decode(json_encode($item), true) : $item;
        }, $data);
    }

    /**
     * Get supported entity types
     */
    public function getSupportedEntityTypes(): array
    {
        return array_keys($this->entityModelMap);
    }

    /**
     * Check if entity type is supported
     */
    public function isEntityTypeSupported(string $entityType): bool
    {
        return isset($this->entityModelMap[$entityType]);
    }
}
