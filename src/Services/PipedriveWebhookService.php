<?php

namespace Keggermont\LaravelPipedrive\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Keggermont\LaravelPipedrive\Events\PipedriveWebhookReceived;
use Keggermont\LaravelPipedrive\Traits\EmitsPipedriveEvents;
use Keggermont\LaravelPipedrive\Models\{
    PipedriveActivity, PipedriveDeal, PipedriveFile, PipedriveNote,
    PipedriveOrganization, PipedrivePerson, PipedrivePipeline,
    PipedriveProduct, PipedriveStage, PipedriveUser, PipedriveGoal
};

class PipedriveWebhookService
{
    use EmitsPipedriveEvents;
    /**
     * Model mapping for webhook objects
     */
    protected array $modelMap = [
        'activity' => PipedriveActivity::class,
        'deal' => PipedriveDeal::class,
        'file' => PipedriveFile::class,
        'note' => PipedriveNote::class,
        'organization' => PipedriveOrganization::class,
        'person' => PipedrivePerson::class,
        'pipeline' => PipedrivePipeline::class,
        'product' => PipedriveProduct::class,
        'stage' => PipedriveStage::class,
        'user' => PipedriveUser::class,
        'goal' => PipedriveGoal::class,
    ];

    /**
     * Process incoming webhook data
     */
    public function processWebhook(array $webhookData): array
    {
        $meta = $webhookData['meta'] ?? [];
        $version = $meta['version'] ?? '1.0';

        // Extract data based on webhook version
        if ($version === '2.0') {
            // Webhooks v2.0 format
            $action = $meta['action'] ?? null;
            $object = $meta['entity'] ?? null;
            $objectId = $meta['entity_id'] ?? null;
        } else {
            // Webhooks v1.0 format (legacy)
            $action = $meta['action'] ?? null;
            $object = $meta['object'] ?? null;
            $objectId = $meta['id'] ?? null;
        }

        // Fire webhook received event
        Event::dispatch(new PipedriveWebhookReceived($webhookData));

        // Skip processing if auto-sync is disabled
        if (!config('pipedrive.webhooks.auto_sync', true)) {
            return [
                'processed' => false,
                'action' => 'skipped',
                'reason' => 'Auto-sync disabled',
            ];
        }

        // Skip unsupported objects
        if (!isset($this->modelMap[$object])) {
            Log::info('Pipedrive webhook: Unsupported object type', [
                'object' => $object,
                'action' => $action,
                'id' => $objectId,
            ]);

            return [
                'processed' => false,
                'action' => 'skipped',
                'reason' => 'Unsupported object type',
            ];
        }

        $modelClass = $this->modelMap[$object];

        try {
            switch ($action) {
                case 'added':
                case 'create':
                case 'updated':
                case 'change':
                    return $this->handleCreateOrUpdate($modelClass, $webhookData);

                case 'deleted':
                case 'delete':
                    return $this->handleDelete($modelClass, $webhookData);

                case 'merged':
                    return $this->handleMerge($modelClass, $webhookData);

                default:
                    Log::info('Pipedrive webhook: Unsupported action', [
                        'action' => $action,
                        'object' => $object,
                        'id' => $objectId,
                    ]);

                    return [
                        'processed' => false,
                        'action' => 'skipped',
                        'reason' => 'Unsupported action',
                    ];
            }
        } catch (\Exception $e) {
            Log::error('Pipedrive webhook processing error', [
                'action' => $action,
                'object' => $object,
                'id' => $objectId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle create or update webhook
     */
    protected function handleCreateOrUpdate(string $modelClass, array $webhookData): array
    {
        $meta = $webhookData['meta'] ?? [];
        $version = $meta['version'] ?? '1.0';
        $action = $meta['action'];

        // Extract current data based on webhook version
        if ($version === '2.0') {
            // Webhooks v2.0 format
            $current = $webhookData['data'] ?? null;
        } else {
            // Webhooks v1.0 format (legacy)
            $current = $webhookData['current'] ?? null;
        }

        if (empty($current) || !isset($current['id'])) {
            throw new \InvalidArgumentException('Invalid webhook data: missing current object data');
        }

        // Use the model's prepareDataManually method if available
        if (method_exists($modelClass, 'prepareDataManually')) {
            $preparedData = $modelClass::prepareDataManually($current);
        } else {
            // Fallback to basic preparation
            $preparedData = $this->prepareBasicData($current);
        }

        // Create or update the record
        $model = $modelClass::updateOrCreate(
            ['pipedrive_id' => $current['id']],
            $preparedData
        );

        $wasCreated = $model->wasRecentlyCreated;

        // Emit specific CRUD events
        $entityType = ($version === '2.0') ? ($meta['entity'] ?? 'unknown') : ($meta['object'] ?? 'unknown');
        if ($wasCreated) {
            $this->emitEntityCreated($entityType, $model, $current, 'webhook', [
                'webhook_action' => $action,
                'change_source' => $meta['change_source'] ?? null,
                'user_id' => $meta['user_id'] ?? null,
                'company_id' => $meta['company_id'] ?? null,
            ]);
        } else {
            // Extract changes for update event
            $previous = $webhookData['previous'] ?? [];
            $changes = $this->extractWebhookChanges($current, $previous);
            $this->emitEntityUpdated($entityType, $model, $current, $changes, 'webhook', [
                'webhook_action' => $action,
                'change_source' => $meta['change_source'] ?? null,
                'user_id' => $meta['user_id'] ?? null,
                'company_id' => $meta['company_id'] ?? null,
            ]);
        }

        Log::info('Pipedrive webhook: Object synchronized', [
            'action' => $action,
            'object' => $entityType,
            'id' => $current['id'],
            'local_action' => $wasCreated ? 'created' : 'updated',
        ]);

        return [
            'processed' => true,
            'action' => $wasCreated ? 'created' : 'updated',
            'model_id' => $model->id,
            'pipedrive_id' => $current['id'],
        ];
    }

    /**
     * Handle delete webhook
     */
    protected function handleDelete(string $modelClass, array $webhookData): array
    {
        $meta = $webhookData['meta'] ?? [];
        $version = $meta['version'] ?? '1.0';

        // Extract previous data based on webhook version
        if ($version === '2.0') {
            // Webhooks v2.0 format - for delete, previous data is in 'previous' field
            $previous = $webhookData['previous'] ?? null;
        } else {
            // Webhooks v1.0 format (legacy)
            $previous = $webhookData['previous'] ?? null;
        }

        if (empty($previous) || !isset($previous['id'])) {
            throw new \InvalidArgumentException('Invalid webhook data: missing previous object data');
        }

        $pipedriveId = $previous['id'];

        // Find the record to get local ID before deletion
        $existingModel = $modelClass::where('pipedrive_id', $pipedriveId)->first();
        $localId = $existingModel?->id;

        // Delete the record
        $deleted = $modelClass::where('pipedrive_id', $pipedriveId)->delete();

        // Emit delete event
        $entityType = ($version === '2.0') ? ($meta['entity'] ?? 'unknown') : ($meta['object'] ?? 'unknown');
        $this->emitEntityDeleted($entityType, $pipedriveId, $localId, $previous, 'webhook', [
            'webhook_action' => 'deleted',
            'change_source' => $meta['change_source'] ?? null,
            'user_id' => $meta['user_id'] ?? null,
            'company_id' => $meta['company_id'] ?? null,
            'deleted_count' => $deleted,
        ]);

        Log::info('Pipedrive webhook: Object deleted', [
            'object' => $entityType,
            'id' => $pipedriveId,
            'deleted_count' => $deleted,
        ]);

        return [
            'processed' => true,
            'action' => 'deleted',
            'pipedrive_id' => $pipedriveId,
            'deleted_count' => $deleted,
        ];
    }

    /**
     * Handle merge webhook
     */
    protected function handleMerge(string $modelClass, array $webhookData): array
    {
        $current = $webhookData['current'] ?? null;
        $previous = $webhookData['previous'] ?? null;
        $meta = $webhookData['meta'] ?? [];

        if (empty($current) || !isset($current['id'])) {
            throw new \InvalidArgumentException('Invalid webhook data: missing current object data');
        }

        // Update the target record (the one being merged into)
        $createUpdateResult = $this->handleCreateOrUpdate($modelClass, $webhookData);

        // If we have previous data, it represents the record being merged (should be deleted)
        if (!empty($previous) && isset($previous['id']) && $previous['id'] !== $current['id']) {
            $deletedCount = $modelClass::where('pipedrive_id', $previous['id'])->delete();
            
            Log::info('Pipedrive webhook: Merged object cleaned up', [
                'object' => $meta['object'],
                'merged_id' => $previous['id'],
                'target_id' => $current['id'],
                'deleted_count' => $deletedCount,
            ]);
        }

        return [
            'processed' => true,
            'action' => 'merged',
            'target_id' => $current['id'],
            'merged_id' => $previous['id'] ?? null,
            'model_id' => $createUpdateResult['model_id'] ?? null,
        ];
    }

    /**
     * Prepare basic data for models without custom preparation
     */
    protected function prepareBasicData(array $data): array
    {
        return [
            'pipedrive_id' => $data['id'],
            'pipedrive_data' => $data,
            'active_flag' => $data['active_flag'] ?? true,
            'pipedrive_add_time' => isset($data['add_time']) ? 
                \Carbon\Carbon::parse($data['add_time']) : null,
            'pipedrive_update_time' => isset($data['update_time']) ? 
                \Carbon\Carbon::parse($data['update_time']) : null,
        ];
    }
}
