<?php

namespace Keggermont\LaravelPipedrive\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Keggermont\LaravelPipedrive\Events\{
    PipedriveEntityCreated,
    PipedriveEntityUpdated,
    PipedriveEntityDeleted
};

trait EmitsPipedriveEvents
{
    /**
     * Emit a Pipedrive entity created event
     */
    protected function emitEntityCreated(
        string $entityType,
        Model $entity,
        array $originalData = [],
        string $source = 'unknown',
        ?array $metadata = null
    ): void {
        Event::dispatch(new PipedriveEntityCreated(
            $entityType,
            $entity,
            $originalData,
            $source,
            $metadata
        ));
    }

    /**
     * Emit a Pipedrive entity updated event
     */
    protected function emitEntityUpdated(
        string $entityType,
        Model $entity,
        array $originalData = [],
        array $changes = [],
        string $source = 'unknown',
        ?array $metadata = null
    ): void {
        Event::dispatch(new PipedriveEntityUpdated(
            $entityType,
            $entity,
            $originalData,
            $changes,
            $source,
            $metadata
        ));
    }

    /**
     * Emit a Pipedrive entity deleted event
     */
    protected function emitEntityDeleted(
        string $entityType,
        int $pipedriveId,
        ?int $localId = null,
        array $entityData = [],
        string $source = 'unknown',
        ?array $metadata = null
    ): void {
        Event::dispatch(new PipedriveEntityDeleted(
            $entityType,
            $pipedriveId,
            $localId,
            $entityData,
            $source,
            $metadata
        ));
    }

    /**
     * Get the entity type from a model class
     */
    protected function getEntityTypeFromModel(Model $model): string
    {
        $modelClass = get_class($model);
        
        // Map model classes to entity types
        $modelMap = [
            \Keggermont\LaravelPipedrive\Models\PipedriveActivity::class => 'activities',
            \Keggermont\LaravelPipedrive\Models\PipedriveDeal::class => 'deals',
            \Keggermont\LaravelPipedrive\Models\PipedriveFile::class => 'files',
            \Keggermont\LaravelPipedrive\Models\PipedriveGoal::class => 'goals',
            \Keggermont\LaravelPipedrive\Models\PipedriveNote::class => 'notes',
            \Keggermont\LaravelPipedrive\Models\PipedriveOrganization::class => 'organizations',
            \Keggermont\LaravelPipedrive\Models\PipedrivePerson::class => 'persons',
            \Keggermont\LaravelPipedrive\Models\PipedrivePipeline::class => 'pipelines',
            \Keggermont\LaravelPipedrive\Models\PipedriveProduct::class => 'products',
            \Keggermont\LaravelPipedrive\Models\PipedriveStage::class => 'stages',
            \Keggermont\LaravelPipedrive\Models\PipedriveUser::class => 'users',
        ];

        return $modelMap[$modelClass] ?? 'unknown';
    }

    /**
     * Get entity type from model using the getPipedriveEntityName method if available
     */
    protected function getEntityTypeFromModelSafe(Model $model): string
    {
        if (method_exists($model, 'getPipedriveEntityName')) {
            return $model::getPipedriveEntityName();
        }

        return $this->getEntityTypeFromModel($model);
    }

    /**
     * Emit events for a model operation with automatic entity type detection
     */
    protected function emitModelCreated(
        Model $entity,
        array $originalData = [],
        string $source = 'unknown',
        ?array $metadata = null
    ): void {
        $entityType = $this->getEntityTypeFromModelSafe($entity);
        $this->emitEntityCreated($entityType, $entity, $originalData, $source, $metadata);
    }

    /**
     * Emit events for a model update with automatic entity type detection
     */
    protected function emitModelUpdated(
        Model $entity,
        array $originalData = [],
        array $changes = [],
        string $source = 'unknown',
        ?array $metadata = null
    ): void {
        $entityType = $this->getEntityTypeFromModelSafe($entity);
        $this->emitEntityUpdated($entityType, $entity, $originalData, $changes, $source, $metadata);
    }

    /**
     * Emit events for a model deletion with automatic entity type detection
     */
    protected function emitModelDeleted(
        string $modelClass,
        int $pipedriveId,
        ?int $localId = null,
        array $entityData = [],
        string $source = 'unknown',
        ?array $metadata = null
    ): void {
        // Create a temporary model instance to get the entity type
        $tempModel = new $modelClass();
        $entityType = $this->getEntityTypeFromModelSafe($tempModel);
        
        $this->emitEntityDeleted($entityType, $pipedriveId, $localId, $entityData, $source, $metadata);
    }

    /**
     * Extract changes from Laravel model dirty attributes
     */
    protected function extractModelChanges(Model $model): array
    {
        $changes = [];
        $dirty = $model->getDirty();
        $original = $model->getOriginal();

        foreach ($dirty as $field => $newValue) {
            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $newValue,
            ];
        }

        return $changes;
    }

    /**
     * Create metadata array with common information
     */
    protected function createEventMetadata(array $additional = []): array
    {
        return array_merge([
            'timestamp' => now()->toISOString(),
            'user_id' => auth()->id(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ], $additional);
    }

    /**
     * Emit events for webhook data
     */
    protected function emitWebhookEvents(
        string $action,
        string $entityType,
        array $webhookData,
        ?Model $model = null,
        ?int $localId = null
    ): void {
        $meta = $webhookData['meta'] ?? [];
        $current = $webhookData['current'] ?? [];
        $previous = $webhookData['previous'] ?? [];

        $metadata = $this->createEventMetadata([
            'webhook_action' => $action,
            'webhook_object' => $meta['object'] ?? null,
            'webhook_id' => $meta['id'] ?? null,
            'change_source' => $meta['change_source'] ?? null,
            'user_id' => $meta['user_id'] ?? null,
            'company_id' => $meta['company_id'] ?? null,
            'is_bulk_update' => $meta['is_bulk_update'] ?? false,
        ]);

        switch ($action) {
            case 'added':
                if ($model) {
                    $this->emitEntityCreated($entityType, $model, $current, 'webhook', $metadata);
                }
                break;

            case 'updated':
                if ($model) {
                    // Extract changes from webhook data
                    $changes = $this->extractWebhookChanges($current, $previous);
                    $this->emitEntityUpdated($entityType, $model, $current, $changes, 'webhook', $metadata);
                }
                break;

            case 'deleted':
                $pipedriveId = $meta['id'] ?? ($previous['id'] ?? null);
                if ($pipedriveId) {
                    $this->emitEntityDeleted($entityType, $pipedriveId, $localId, $previous, 'webhook', $metadata);
                }
                break;
        }
    }

    /**
     * Extract changes from webhook current/previous data
     */
    protected function extractWebhookChanges(array $current, array $previous): array
    {
        $changes = [];

        foreach ($current as $field => $newValue) {
            $oldValue = $previous[$field] ?? null;
            
            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }
}
