<?php

namespace Keggermont\LaravelPipedrive\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Keggermont\LaravelPipedrive\Models\PipedriveEntityLink;
use Keggermont\LaravelPipedrive\Traits\EmitsPipedriveEvents;

class PipedriveMergeDetectionService
{
    use EmitsPipedriveEvents;

    protected int $detectionWindowSeconds;
    protected string $cachePrefix;

    public function __construct()
    {
        $this->detectionWindowSeconds = config('pipedrive.merge.detection_window_seconds', 30);
        $this->cachePrefix = 'pipedrive_merge_detection:';
    }

    /**
     * Track a webhook event for potential merge detection
     */
    public function trackWebhookEvent(array $webhookData): void
    {
        if (!config('pipedrive.merge.enable_heuristic_detection', true)) {
            return;
        }

        $meta = $webhookData['meta'] ?? [];
        $correlationId = $meta['correlation_id'] ?? null;
        $action = $meta['action'] ?? null;
        $entityType = $meta['entity'] ?? $meta['object'] ?? null;
        $entityId = $meta['entity_id'] ?? $meta['id'] ?? null;

        if (!$correlationId || !$entityType || !$entityId) {
            return;
        }

        // Store the event in cache for correlation analysis
        $cacheKey = $this->cachePrefix . $correlationId;
        $events = Cache::get($cacheKey, []);
        
        $events[] = [
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'timestamp' => now()->timestamp,
            'webhook_data' => $webhookData,
        ];

        // Store for the detection window duration
        Cache::put($cacheKey, $events, $this->detectionWindowSeconds);

        // Analyze for potential merges
        $this->analyzeForMerge($correlationId, $events);
    }

    /**
     * Analyze events for potential merge patterns
     */
    protected function analyzeForMerge(string $correlationId, array $events): void
    {
        // Look for the pattern: update(s) followed by delete
        $entitiesByType = [];
        
        foreach ($events as $event) {
            $type = $event['entity_type'];
            $id = $event['entity_id'];
            
            if (!isset($entitiesByType[$type])) {
                $entitiesByType[$type] = [];
            }
            
            if (!isset($entitiesByType[$type][$id])) {
                $entitiesByType[$type][$id] = [];
            }
            
            $entitiesByType[$type][$id][] = $event;
        }

        // Check each entity type for merge patterns
        foreach ($entitiesByType as $entityType => $entities) {
            $this->detectMergeInEntityType($correlationId, $entityType, $entities);
        }
    }

    /**
     * Detect merge pattern within a specific entity type
     */
    protected function detectMergeInEntityType(string $correlationId, string $entityType, array $entities): void
    {
        $deletedEntities = [];
        $updatedEntities = [];

        foreach ($entities as $entityId => $events) {
            $hasDelete = false;
            $hasUpdate = false;

            foreach ($events as $event) {
                if ($event['action'] === 'delete') {
                    $hasDelete = true;
                }
                if (in_array($event['action'], ['change', 'updated'])) {
                    $hasUpdate = true;
                }
            }

            if ($hasDelete) {
                $deletedEntities[] = $entityId;
            }
            if ($hasUpdate) {
                $updatedEntities[] = $entityId;
            }
        }

        // If we have exactly one deleted entity and at least one updated entity of the same type
        // this could be a merge
        if (count($deletedEntities) === 1 && count($updatedEntities) >= 1) {
            $mergedId = $deletedEntities[0];
            
            // Find the most likely surviving entity (the one that was updated but not deleted)
            $survivingCandidates = array_diff($updatedEntities, $deletedEntities);
            
            if (!empty($survivingCandidates)) {
                // Take the first candidate as the surviving entity
                $survivingId = $survivingCandidates[0];
                
                Log::info('Detected potential merge via heuristic analysis', [
                    'correlation_id' => $correlationId,
                    'entity_type' => $entityType,
                    'merged_id' => $mergedId,
                    'surviving_id' => $survivingId,
                    'all_events' => count($entities),
                ]);

                // Process the merge
                $this->processMerge($entityType, $mergedId, $survivingId, $correlationId);
            }
        }
    }

    /**
     * Process a detected merge
     */
    protected function processMerge(string $entityType, int $mergedId, int $survivingId, string $correlationId): void
    {
        try {
            // Get the surviving entity model
            $modelClass = $this->getModelClassForEntityType($entityType);
            $survivingEntity = null;

            if ($modelClass) {
                $survivingEntity = $modelClass::where('pipedrive_id', $survivingId)->first();
            }

            // Initialize migration results
            $migrationResults = [
                'migrated' => 0,
                'skipped' => 0,
                'conflicts' => 0,
                'errors' => 0,
                'auto_migration_enabled' => false,
            ];

            // Check if automatic migration is enabled
            if (config('pipedrive.merge.auto_migrate_relations', true)) {
                // Migrate entity relations automatically
                $migrationStrategy = config('pipedrive.merge.strategy', 'keep_both');
                $migrationResults = PipedriveEntityLink::migrateEntityRelations(
                    $entityType,
                    $mergedId,
                    $survivingId,
                    $migrationStrategy
                );
                $migrationResults['auto_migration_enabled'] = true;
            } else {
                Log::info('Automatic relation migration disabled for heuristic merge', [
                    'entity_type' => $entityType,
                    'merged_id' => $mergedId,
                    'surviving_id' => $survivingId,
                    'note' => 'Set PIPEDRIVE_MERGE_AUTO_MIGRATE=true to enable automatic migration',
                ]);
            }

            // Emit the merged event (always emitted, regardless of auto-migration setting)
            $this->emitEntityMerged(
                $entityType,
                $mergedId,
                $survivingId,
                $survivingEntity,
                [],
                'webhook_heuristic',
                [
                    'correlation_id' => $correlationId,
                    'detection_method' => 'heuristic',
                    'migration_results' => $migrationResults,
                    'auto_migration_enabled' => $migrationResults['auto_migration_enabled'],
                ],
                $migrationResults['migrated'] ?? 0
            );

            Log::info('Processed heuristic merge', [
                'entity_type' => $entityType,
                'merged_id' => $mergedId,
                'surviving_id' => $survivingId,
                'correlation_id' => $correlationId,
                'migration_results' => $migrationResults,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process heuristic merge', [
                'entity_type' => $entityType,
                'merged_id' => $mergedId,
                'surviving_id' => $survivingId,
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get model class for entity type
     */
    protected function getModelClassForEntityType(string $entityType): ?string
    {
        $modelMap = [
            'deals' => \Keggermont\LaravelPipedrive\Models\PipedriveDeal::class,
            'persons' => \Keggermont\LaravelPipedrive\Models\PipedrivePerson::class,
            'organizations' => \Keggermont\LaravelPipedrive\Models\PipedriveOrganization::class,
            'activities' => \Keggermont\LaravelPipedrive\Models\PipedriveActivity::class,
            'products' => \Keggermont\LaravelPipedrive\Models\PipedriveProduct::class,
            'files' => \Keggermont\LaravelPipedrive\Models\PipedriveFile::class,
            'notes' => \Keggermont\LaravelPipedrive\Models\PipedriveNote::class,
            'users' => \Keggermont\LaravelPipedrive\Models\PipedriveUser::class,
            'pipelines' => \Keggermont\LaravelPipedrive\Models\PipedrivePipeline::class,
            'stages' => \Keggermont\LaravelPipedrive\Models\PipedriveStage::class,
            'goals' => \Keggermont\LaravelPipedrive\Models\PipedriveGoal::class,
        ];

        return $modelMap[$entityType] ?? null;
    }

    /**
     * Clear detection cache for a correlation ID
     */
    public function clearDetectionCache(string $correlationId): void
    {
        Cache::forget($this->cachePrefix . $correlationId);
    }
}
