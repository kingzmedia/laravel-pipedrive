<?php

namespace Keggermont\LaravelPipedrive\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;
use Keggermont\LaravelPipedrive\Models\PipedriveCustomField;
use Keggermont\LaravelPipedrive\Services\PipedriveCustomFieldService;
use Keggermont\LaravelPipedrive\Traits\EmitsPipedriveEvents;

class PushToPipedriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, EmitsPipedriveEvents;

    public Model $model;
    public array $modifications;
    public array $customFields;
    public string $entityType;
    public int $pipedriveId;
    public ?string $queue;
    public int $maxRetries;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Model $model,
        array $modifications,
        array $customFields,
        string $entityType,
        int $pipedriveId,
        ?string $queue = null,
        int $maxRetries = 3
    ) {
        $this->model = $model;
        $this->modifications = $modifications;
        $this->customFields = $customFields;
        $this->entityType = $entityType;
        $this->pipedriveId = $pipedriveId;
        $this->maxRetries = $maxRetries;
        
        if ($queue) {
            $this->onQueue($queue);
        }
        
        // Set retry attempts
        $this->tries = $maxRetries;
    }

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        try {
            Log::info('Starting Pipedrive push job', [
                'model' => get_class($this->model),
                'model_id' => $this->model->getKey(),
                'entity_type' => $this->entityType,
                'pipedrive_id' => $this->pipedriveId,
                'modifications' => array_keys($this->modifications),
                'custom_fields' => array_keys($this->customFields),
                'attempt' => $this->attempts(),
            ]);

            // Get Pipedrive client
            $authService = app(PipedriveAuthService::class);
            $pipedrive = $authService->getPipedriveInstance();

            // Prepare data for Pipedrive API
            $updateData = $this->prepareDataForPipedrive();

            // Call Pipedrive API to update the entity
            $response = $this->callPipedriveUpdate($pipedrive, $updateData);

            if (!$response || !isset($response['success']) || !$response['success']) {
                throw new \Exception('Pipedrive API update failed: ' . ($response['error'] ?? 'Unknown error'));
            }

            // Update local database with the modifications
            $this->updateLocalEntity();

            // Emit success event
            $this->emitModelUpdated(
                $this->model,
                array_merge($this->modifications, $this->customFields),
                $this->extractModelChanges($this->model),
                'job',
                [
                    'job_class' => self::class,
                    'attempt' => $this->attempts(),
                    'queue' => $this->queue,
                ]
            );

            $result = [
                'success' => true,
                'pipedrive_id' => $this->pipedriveId,
                'entity_type' => $this->entityType,
                'updated_fields' => array_merge(array_keys($this->modifications), array_keys($this->customFields)),
                'response' => $response,
                'processed_via' => 'job',
                'attempt' => $this->attempts(),
            ];

            Log::info('Successfully pushed modifications to Pipedrive via job', $result);

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to push modifications to Pipedrive via job', [
                'model' => get_class($this->model),
                'model_id' => $this->model->getKey(),
                'entity_type' => $this->entityType,
                'pipedrive_id' => $this->pipedriveId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
                'modifications' => $this->modifications,
                'custom_fields' => $this->customFields,
            ]);

            // If this is the last attempt, mark as failed
            if ($this->attempts() >= $this->tries) {
                $this->markAsFailed($e);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Pipedrive push job failed permanently', [
            'model' => get_class($this->model),
            'model_id' => $this->model->getKey(),
            'entity_type' => $this->entityType,
            'pipedrive_id' => $this->pipedriveId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'modifications' => $this->modifications,
            'custom_fields' => $this->customFields,
        ]);

        // You could emit a failed event here or send notifications
        // Event::dispatch(new PipedrivePushFailed($this->model, $exception));
    }

    /**
     * Prepare data for Pipedrive API call
     */
    protected function prepareDataForPipedrive(): array
    {
        $updateData = [];

        // Add basic field modifications
        foreach ($this->modifications as $field => $value) {
            $updateData[$field] = $value;
        }

        // Add custom fields with proper key mapping
        if (!empty($this->customFields)) {
            foreach ($this->customFields as $fieldName => $value) {
                // Try to find the field by name first
                $field = PipedriveCustomField::where('entity_type', $this->entityType)
                    ->where('name', $fieldName)
                    ->active()
                    ->first();

                if (!$field) {
                    // Try to find by key if name doesn't work
                    $field = PipedriveCustomField::where('entity_type', $this->entityType)
                        ->where('key', $fieldName)
                        ->active()
                        ->first();
                }

                if ($field) {
                    // Use the Pipedrive key for the API call
                    $updateData[$field->key] = $this->formatCustomFieldValue($field, $value);
                } else {
                    Log::warning("Custom field not found for entity type {$this->entityType}", [
                        'field_name' => $fieldName,
                        'job_id' => $this->job->getJobId() ?? 'unknown',
                    ]);
                }
            }
        }

        return $updateData;
    }

    /**
     * Call Pipedrive API to update entity
     */
    protected function callPipedriveUpdate($pipedrive, array $updateData): ?array
    {
        try {
            $response = match ($this->entityType) {
                'deals' => $pipedrive->deals->update($this->pipedriveId, $updateData),
                'persons' => $pipedrive->persons->update($this->pipedriveId, $updateData),
                'organizations' => $pipedrive->organizations->update($this->pipedriveId, $updateData),
                'activities' => $pipedrive->activities->update($this->pipedriveId, $updateData),
                'products' => $pipedrive->products->update($this->pipedriveId, $updateData),
                'notes' => $pipedrive->notes->update($this->pipedriveId, $updateData),
                default => throw new \Exception("Unsupported entity type for update: {$this->entityType}")
            };

            // Convert response to array if it's an object
            if (is_object($response)) {
                $response = json_decode(json_encode($response), true);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error("Pipedrive API update failed for {$this->entityType} {$this->pipedriveId} in job", [
                'error' => $e->getMessage(),
                'update_data' => $updateData,
                'job_id' => $this->job->getJobId() ?? 'unknown',
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update local entity with modifications
     */
    protected function updateLocalEntity(): void
    {
        // Refresh the model to get latest data
        $this->model->refresh();

        // Update basic fields
        foreach ($this->modifications as $field => $value) {
            if (in_array($field, $this->model->getFillable())) {
                $this->model->$field = $value;
            }
        }

        // Update custom fields in pipedrive_data
        if (!empty($this->customFields)) {
            $pipedriveData = $this->model->pipedrive_data ?? [];
            
            foreach ($this->customFields as $fieldName => $value) {
                // Store both by name and by key for easier access
                $pipedriveData[$fieldName] = $value;
                
                // Also try to store by Pipedrive key if we can find it
                $field = PipedriveCustomField::where('entity_type', $this->entityType)
                    ->where('name', $fieldName)
                    ->active()
                    ->first();
                
                if ($field) {
                    $pipedriveData[$field->key] = $value;
                }
            }
            
            $this->model->pipedrive_data = $pipedriveData;
        }

        // Update the update timestamp
        if (method_exists($this->model, 'setPipedriveUpdateTime')) {
            $this->model->setPipedriveUpdateTime(now());
        } elseif (in_array('pipedrive_update_time', $this->model->getFillable())) {
            $this->model->pipedrive_update_time = now();
        }

        $this->model->save();
    }

    /**
     * Format custom field value for Pipedrive API
     */
    protected function formatCustomFieldValue(PipedriveCustomField $field, $value)
    {
        return match ($field->field_type) {
            'date' => $value instanceof \Carbon\Carbon ? $value->format('Y-m-d') : $value,
            'datetime' => $value instanceof \Carbon\Carbon ? $value->format('Y-m-d H:i:s') : $value,
            'monetary' => is_numeric($value) ? (float) $value : $value,
            'int' => is_numeric($value) ? (int) $value : $value,
            'double' => is_numeric($value) ? (float) $value : $value,
            'varchar', 'text' => (string) $value,
            'enum', 'set' => $value, // These should be option IDs
            default => $value,
        };
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
     * Mark the job as failed and handle cleanup
     */
    protected function markAsFailed(\Exception $exception): void
    {
        // You could update a status field on the model here
        // Or create a failed job record for tracking
        
        Log::error('Pipedrive push job marked as permanently failed', [
            'model' => get_class($this->model),
            'model_id' => $this->model->getKey(),
            'exception' => $exception->getMessage(),
        ]);
    }
}
