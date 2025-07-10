<?php

namespace Keggermont\LaravelPipedrive\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Keggermont\LaravelPipedrive\Models\PipedriveEntityLink;
use Keggermont\LaravelPipedrive\Models\PipedriveCustomField;
use Keggermont\LaravelPipedrive\Enums\PipedriveEntityType;
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;
use Keggermont\LaravelPipedrive\Services\PipedriveCustomFieldService;
use Keggermont\LaravelPipedrive\Jobs\PushToPipedriveJob;
use Keggermont\LaravelPipedrive\Models\{
    PipedriveActivity, PipedriveDeal, PipedriveFile, PipedriveNote,
    PipedriveOrganization, PipedrivePerson, PipedrivePipeline,
    PipedriveProduct, PipedriveStage, PipedriveUser, PipedriveGoal
};

trait HasPipedriveEntity
{
    /**
     * Get the default Pipedrive entity type for this model
     * Override this method in your model to set the default entity type
     */
    public function getDefaultPipedriveEntityType(): PipedriveEntityType
    {
        // Check if the model has a property defined
        if (property_exists($this, 'pipedriveEntityType')) {
            return is_string($this->pipedriveEntityType)
                ? PipedriveEntityType::fromString($this->pipedriveEntityType)
                : $this->pipedriveEntityType;
        }

        // Auto-suggest based on model name
        $suggestions = PipedriveEntityType::getSuggestedForModel(static::class);
        return $suggestions[0] ?? PipedriveEntityType::DEALS;
    }

    /**
     * Get the default entity type as string
     */
    public function getDefaultPipedriveEntityTypeString(): string
    {
        return $this->getDefaultPipedriveEntityType()->value;
    }

    /**
     * Get all Pipedrive entity links for this model
     */
    public function pipedriveEntityLinks(): MorphMany
    {
        return $this->morphMany(PipedriveEntityLink::class, 'linkable');
    }

    /**
     * Get the primary Pipedrive entity link
     */
    public function primaryPipedriveEntityLink(): MorphOne
    {
        return $this->morphOne(PipedriveEntityLink::class, 'linkable')
                    ->where('is_primary', true)
                    ->where('is_active', true);
    }

    /**
     * Get active Pipedrive entity links
     */
    public function activePipedriveEntityLinks(): MorphMany
    {
        return $this->pipedriveEntityLinks()->active();
    }

    /**
     * Link this model to the default Pipedrive entity type
     */
    public function linkToPipedriveEntity(
        int $entityId,
        bool $isPrimary = true,
        array $metadata = []
    ): PipedriveEntityLink {
        return $this->linkToSpecificPipedriveEntity(
            $this->getDefaultPipedriveEntityTypeString(),
            $entityId,
            $isPrimary,
            $metadata
        );
    }

    /**
     * Link this model to a specific Pipedrive entity type
     */
    public function linkToSpecificPipedriveEntity(
        string $entityType,
        int $entityId,
        bool $isPrimary = false,
        array $metadata = []
    ): PipedriveEntityLink {
        // If setting as primary, unset other primary links
        if ($isPrimary) {
            $this->pipedriveEntityLinks()->update(['is_primary' => false]);
        }

        $link = $this->pipedriveEntityLinks()->updateOrCreate(
            [
                'pipedrive_entity_type' => $entityType,
                'pipedrive_entity_id' => $entityId,
            ],
            [
                'is_primary' => $isPrimary,
                'is_active' => true,
                'metadata' => $metadata,
                'sync_status' => 'pending',
            ]
        );

        // Try to sync local Pipedrive model reference
        $link->syncLocalPipedriveModel();

        return $link;
    }

    /**
     * Link to a Pipedrive Deal
     */
    public function linkToPipedriveDeal(int $dealId, bool $isPrimary = false, array $metadata = []): PipedriveEntityLink
    {
        return $this->linkToSpecificPipedriveEntity('deals', $dealId, $isPrimary, $metadata);
    }

    /**
     * Link to a Pipedrive Person
     */
    public function linkToPipedrivePerson(int $personId, bool $isPrimary = false, array $metadata = []): PipedriveEntityLink
    {
        return $this->linkToSpecificPipedriveEntity('persons', $personId, $isPrimary, $metadata);
    }

    /**
     * Link to a Pipedrive Organization
     */
    public function linkToPipedriveOrganization(int $orgId, bool $isPrimary = false, array $metadata = []): PipedriveEntityLink
    {
        return $this->linkToSpecificPipedriveEntity('organizations', $orgId, $isPrimary, $metadata);
    }

    /**
     * Link to a Pipedrive Activity
     */
    public function linkToPipedriveActivity(int $activityId, bool $isPrimary = false, array $metadata = []): PipedriveEntityLink
    {
        return $this->linkToSpecificPipedriveEntity('activities', $activityId, $isPrimary, $metadata);
    }

    /**
     * Link to a Pipedrive Product
     */
    public function linkToPipedriveProduct(int $productId, bool $isPrimary = false, array $metadata = []): PipedriveEntityLink
    {
        return $this->linkToSpecificPipedriveEntity('products', $productId, $isPrimary, $metadata);
    }

    /**
     * Unlink from the default Pipedrive entity type
     */
    public function unlinkFromPipedriveEntity(int $entityId): bool
    {
        return $this->unlinkFromSpecificPipedriveEntity(
            $this->getDefaultPipedriveEntityTypeString(),
            $entityId
        );
    }

    /**
     * Unlink from a specific Pipedrive entity
     */
    public function unlinkFromSpecificPipedriveEntity(string $entityType, int $entityId): bool
    {
        return $this->pipedriveEntityLinks()
                    ->where('pipedrive_entity_type', $entityType)
                    ->where('pipedrive_entity_id', $entityId)
                    ->delete() > 0;
    }

    /**
     * Unlink from all Pipedrive entities
     */
    public function unlinkFromAllPipedriveEntities(): int
    {
        return $this->pipedriveEntityLinks()->delete();
    }

    /**
     * Get linked Pipedrive entities of the default type
     */
    public function getPipedriveEntities(): Collection
    {
        return $this->getSpecificPipedriveEntities($this->getDefaultPipedriveEntityTypeString());
    }

    /**
     * Get linked Pipedrive entities of a specific type
     */
    public function getSpecificPipedriveEntities(string $entityType): Collection
    {
        return $this->pipedriveEntityLinks()
                    ->where('pipedrive_entity_type', $entityType)
                    ->active()
                    ->get();
    }

    /**
     * Get the primary entity of the default type
     */
    public function getPrimaryPipedriveEntity(): ?Model
    {
        $link = $this->pipedriveEntityLinks()
                     ->where('pipedrive_entity_type', $this->getDefaultPipedriveEntityTypeString())
                     ->primary()
                     ->active()
                     ->first();

        return $link ? $link->getLocalPipedriveModel() : null;
    }

    /**
     * Get linked Pipedrive deals
     */
    public function getPipedriveDeals(): Collection
    {
        return $this->getSpecificPipedriveEntities('deals');
    }

    /**
     * Get the primary Pipedrive deal
     */
    public function getPrimaryPipedriveDeal(): ?PipedriveDeal
    {
        $link = $this->pipedriveEntityLinks()
                     ->where('pipedrive_entity_type', 'deals')
                     ->primary()
                     ->active()
                     ->first();

        return $link ? $link->getLocalPipedriveModel() : null;
    }

    /**
     * Get linked Pipedrive persons
     */
    public function getPipedrivePersons(): Collection
    {
        return $this->getSpecificPipedriveEntities('persons');
    }

    /**
     * Get the primary Pipedrive person
     */
    public function getPrimaryPipedrivePerson(): ?PipedrivePerson
    {
        $link = $this->pipedriveEntityLinks()
                     ->where('pipedrive_entity_type', 'persons')
                     ->primary()
                     ->active()
                     ->first();

        return $link ? $link->getLocalPipedriveModel() : null;
    }

    /**
     * Get linked Pipedrive organizations
     */
    public function getPipedriveOrganizations(): Collection
    {
        return $this->getSpecificPipedriveEntities('organizations');
    }

    /**
     * Get the primary Pipedrive organization
     */
    public function getPrimaryPipedriveOrganization(): ?PipedriveOrganization
    {
        $link = $this->pipedriveEntityLinks()
                     ->where('pipedrive_entity_type', 'organizations')
                     ->primary()
                     ->active()
                     ->first();

        return $link ? $link->getLocalPipedriveModel() : null;
    }

    /**
     * Check if this model is linked to an entity of the default type
     */
    public function isLinkedToPipedriveEntity(int $entityId): bool
    {
        return $this->isLinkedToSpecificPipedriveEntity(
            $this->getDefaultPipedriveEntityTypeString(),
            $entityId
        );
    }

    /**
     * Check if this model is linked to a specific Pipedrive entity
     */
    public function isLinkedToSpecificPipedriveEntity(string $entityType, int $entityId): bool
    {
        return $this->pipedriveEntityLinks()
                    ->where('pipedrive_entity_type', $entityType)
                    ->where('pipedrive_entity_id', $entityId)
                    ->active()
                    ->exists();
    }

    /**
     * Check if this model is linked to any Pipedrive entity
     */
    public function hasAnyPipedriveEntity(): bool
    {
        return $this->pipedriveEntityLinks()->active()->exists();
    }

    /**
     * Get all linked Pipedrive entity types
     */
    public function getLinkedPipedriveEntityTypes(): Collection
    {
        return $this->pipedriveEntityLinks()
                    ->active()
                    ->pluck('pipedrive_entity_type')
                    ->unique();
    }

    /**
     * Sync all linked Pipedrive entities
     */
    public function syncPipedriveEntities(): Collection
    {
        $results = collect();

        $this->pipedriveEntityLinks()->active()->each(function ($link) use ($results) {
            $synced = $link->syncLocalPipedriveModel();
            $results->push([
                'link_id' => $link->id,
                'entity_type' => $link->pipedrive_entity_type,
                'entity_id' => $link->pipedrive_entity_id,
                'synced' => $synced,
            ]);

            if ($synced) {
                $link->markAsSynced();
            }
        });

        return $results;
    }

    /**
     * Get Pipedrive entity statistics
     */
    public function getPipedriveEntityStats(): array
    {
        $links = $this->pipedriveEntityLinks()->active()->get();

        return [
            'total_links' => $links->count(),
            'by_entity_type' => $links->groupBy('pipedrive_entity_type')->map->count(),
            'primary_links' => $links->where('is_primary', true)->count(),
            'synced_links' => $links->where('sync_status', 'synced')->count(),
            'pending_links' => $links->where('sync_status', 'pending')->count(),
            'error_links' => $links->where('sync_status', 'error')->count(),
        ];
    }

    /**
     * Push modifications to Pipedrive and update local database
     * By default, uses a job for async processing. Set $forceSync to true for immediate execution.
     */
    public function pushToPipedrive(
        array $modifications,
        array $customFields = [],
        bool $forceSync = false,
        ?string $queue = null,
        int $maxRetries = 3
    ): array {
        $primaryEntity = $this->getPrimaryPipedriveEntity();

        if (!$primaryEntity) {
            throw new \Exception('No primary Pipedrive entity found for this model');
        }

        $entityType = $this->getDefaultPipedriveEntityTypeString();
        $pipedriveId = $primaryEntity->pipedrive_id;

        if ($forceSync) {
            // Execute synchronously
            return $this->pushToPipedriveSynchronously($modifications, $customFields, $entityType, $pipedriveId);
        } else {
            // Execute asynchronously via job
            return $this->pushToPipedriveAsynchronously($modifications, $customFields, $entityType, $pipedriveId, $queue, $maxRetries);
        }
    }

    /**
     * Push modifications to Pipedrive synchronously (immediate execution)
     */
    public function pushToPipedriveSynchronously(array $modifications, array $customFields, string $entityType, int $pipedriveId): array
    {
        try {
            // Get Pipedrive client
            $authService = app(PipedriveAuthService::class);
            $pipedrive = $authService->getPipedriveInstance();

            // Prepare data for Pipedrive API
            $updateData = $this->prepareDataForPipedrive($modifications, $customFields, $entityType);

            // Call Pipedrive API to update the entity
            $response = $this->callPipedriveUpdate($pipedrive, $entityType, $pipedriveId, $updateData);

            if (!$response || !isset($response['success']) || !$response['success']) {
                throw new \Exception('Pipedrive API update failed: ' . ($response['error'] ?? 'Unknown error'));
            }

            // Update local database with the modifications
            $primaryEntity = $this->getPrimaryPipedriveEntity();
            $this->updateLocalEntity($primaryEntity, $modifications, $customFields);

            Log::info('Successfully pushed modifications to Pipedrive (sync)', [
                'model' => get_class($this),
                'model_id' => $this->getKey(),
                'entity_type' => $entityType,
                'pipedrive_id' => $pipedriveId,
                'modifications' => array_keys($modifications),
                'custom_fields' => array_keys($customFields),
            ]);

            return [
                'success' => true,
                'pipedrive_id' => $pipedriveId,
                'entity_type' => $entityType,
                'updated_fields' => array_merge(array_keys($modifications), array_keys($customFields)),
                'response' => $response,
                'processed_via' => 'sync',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to push modifications to Pipedrive (sync)', [
                'model' => get_class($this),
                'model_id' => $this->getKey(),
                'entity_type' => $entityType,
                'pipedrive_id' => $pipedriveId,
                'error' => $e->getMessage(),
                'modifications' => $modifications,
                'custom_fields' => $customFields,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'pipedrive_id' => $pipedriveId,
                'entity_type' => $entityType,
                'processed_via' => 'sync',
            ];
        }
    }

    /**
     * Push modifications to Pipedrive asynchronously via job
     */
    public function pushToPipedriveAsynchronously(
        array $modifications,
        array $customFields,
        string $entityType,
        int $pipedriveId,
        ?string $queue = null,
        int $maxRetries = 3
    ): array {
        try {
            // Dispatch the job
            $job = new PushToPipedriveJob(
                $this,
                $modifications,
                $customFields,
                $entityType,
                $pipedriveId,
                $queue,
                $maxRetries
            );

            // Get the queue name for logging
            $queueName = $queue ?? config('queue.default', 'default');

            // Dispatch the job
            dispatch($job);

            Log::info('Dispatched Pipedrive push job', [
                'model' => get_class($this),
                'model_id' => $this->getKey(),
                'entity_type' => $entityType,
                'pipedrive_id' => $pipedriveId,
                'modifications' => array_keys($modifications),
                'custom_fields' => array_keys($customFields),
                'queue' => $queueName,
                'max_retries' => $maxRetries,
            ]);

            return [
                'success' => true,
                'pipedrive_id' => $pipedriveId,
                'entity_type' => $entityType,
                'updated_fields' => array_merge(array_keys($modifications), array_keys($customFields)),
                'processed_via' => 'job',
                'queue' => $queueName,
                'max_retries' => $maxRetries,
                'job_dispatched' => true,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to dispatch Pipedrive push job', [
                'model' => get_class($this),
                'model_id' => $this->getKey(),
                'entity_type' => $entityType,
                'pipedrive_id' => $pipedriveId,
                'error' => $e->getMessage(),
                'modifications' => $modifications,
                'custom_fields' => $customFields,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'pipedrive_id' => $pipedriveId,
                'entity_type' => $entityType,
                'processed_via' => 'job',
                'job_dispatched' => false,
            ];
        }
    }

    /**
     * Display entity details with human-readable custom field names
     */
    public function displayPipedriveDetails(bool $includeCustomFields = true): array
    {
        $primaryEntity = $this->getPrimaryPipedriveEntity();

        if (!$primaryEntity) {
            return [
                'error' => 'No primary Pipedrive entity found for this model',
                'has_entity' => false,
            ];
        }

        $entityType = $this->getDefaultPipedriveEntityTypeString();
        $details = [
            'entity_type' => $entityType,
            'pipedrive_id' => $primaryEntity->pipedrive_id,
            'local_id' => $primaryEntity->id,
            'has_entity' => true,
            'basic_fields' => $this->getBasicFieldsDisplay($primaryEntity),
        ];

        if ($includeCustomFields) {
            $details['custom_fields'] = $this->getCustomFieldsDisplay($primaryEntity, $entityType);
        }

        $details['metadata'] = [
            'created_at' => $primaryEntity->pipedrive_add_time?->format('Y-m-d H:i:s'),
            'updated_at' => $primaryEntity->pipedrive_update_time?->format('Y-m-d H:i:s'),
            'local_created_at' => $primaryEntity->created_at?->format('Y-m-d H:i:s'),
            'local_updated_at' => $primaryEntity->updated_at?->format('Y-m-d H:i:s'),
        ];

        return $details;
    }

    /**
     * Prepare data for Pipedrive API call
     */
    protected function prepareDataForPipedrive(array $modifications, array $customFields, string $entityType): array
    {
        $updateData = [];

        // Add basic field modifications
        foreach ($modifications as $field => $value) {
            $updateData[$field] = $value;
        }

        // Add custom fields with proper key mapping
        if (!empty($customFields)) {
            $customFieldService = app(PipedriveCustomFieldService::class);

            foreach ($customFields as $fieldName => $value) {
                // Try to find the field by name first
                $field = PipedriveCustomField::where('entity_type', $entityType)
                    ->where('name', $fieldName)
                    ->active()
                    ->first();

                if (!$field) {
                    // Try to find by key if name doesn't work
                    $field = PipedriveCustomField::where('entity_type', $entityType)
                        ->where('key', $fieldName)
                        ->active()
                        ->first();
                }

                if ($field) {
                    // Use the Pipedrive key for the API call
                    $updateData[$field->key] = $this->formatCustomFieldValue($field, $value);
                } else {
                    Log::warning("Custom field not found for entity type {$entityType}", [
                        'field_name' => $fieldName,
                        'available_fields' => PipedriveCustomField::where('entity_type', $entityType)
                            ->active()
                            ->pluck('name', 'key')
                            ->toArray()
                    ]);
                }
            }
        }

        return $updateData;
    }

    /**
     * Call Pipedrive API to update entity
     */
    protected function callPipedriveUpdate($pipedrive, string $entityType, int $pipedriveId, array $updateData): ?array
    {
        try {
            $response = match ($entityType) {
                'deals' => $pipedrive->deals->update($pipedriveId, $updateData),
                'persons' => $pipedrive->persons->update($pipedriveId, $updateData),
                'organizations' => $pipedrive->organizations->update($pipedriveId, $updateData),
                'activities' => $pipedrive->activities->update($pipedriveId, $updateData),
                'products' => $pipedrive->products->update($pipedriveId, $updateData),
                'notes' => $pipedrive->notes->update($pipedriveId, $updateData),
                default => throw new \Exception("Unsupported entity type for update: {$entityType}")
            };

            // Convert response to array if it's an object
            if (is_object($response)) {
                $response = json_decode(json_encode($response), true);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error("Pipedrive API update failed for {$entityType} {$pipedriveId}", [
                'error' => $e->getMessage(),
                'update_data' => $updateData,
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
    protected function updateLocalEntity($entity, array $modifications, array $customFields): void
    {
        // Update basic fields
        foreach ($modifications as $field => $value) {
            if (in_array($field, $entity->getFillable())) {
                $entity->$field = $value;
            }
        }

        // Update custom fields in pipedrive_data
        if (!empty($customFields)) {
            $pipedriveData = $entity->pipedrive_data ?? [];

            foreach ($customFields as $fieldName => $value) {
                // Store both by name and by key for easier access
                $pipedriveData[$fieldName] = $value;

                // Also try to store by Pipedrive key if we can find it
                $field = PipedriveCustomField::where('entity_type', $entity::getPipedriveEntityName())
                    ->where('name', $fieldName)
                    ->active()
                    ->first();

                if ($field) {
                    $pipedriveData[$field->key] = $value;
                }
            }

            $entity->pipedrive_data = $pipedriveData;
        }

        // Update the update timestamp
        $entity->pipedrive_update_time = now();
        $entity->save();
    }

    /**
     * Get basic fields display
     */
    protected function getBasicFieldsDisplay($entity): array
    {
        $basicFields = [];
        $fillable = $entity->getFillable();

        foreach ($fillable as $field) {
            if (!in_array($field, ['pipedrive_data', 'pipedrive_id'])) {
                $value = $entity->$field;

                // Format the value for display
                if ($value instanceof \Carbon\Carbon) {
                    $value = $value->format('Y-m-d H:i:s');
                } elseif (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                } elseif (is_null($value)) {
                    $value = 'N/A';
                }

                $basicFields[$field] = $value;
            }
        }

        return $basicFields;
    }

    /**
     * Get custom fields display with human-readable names
     */
    protected function getCustomFieldsDisplay($entity, string $entityType): array
    {
        $customFields = [];
        $pipedriveData = $entity->pipedrive_data ?? [];

        // Get all custom fields for this entity type
        $fieldDefinitions = PipedriveCustomField::where('entity_type', $entityType)
            ->active()
            ->get()
            ->keyBy('key');

        foreach ($pipedriveData as $key => $value) {
            // Skip if this is a basic field or system field
            if (in_array($key, ['id', 'add_time', 'update_time']) || !str_contains($key, '_')) {
                continue;
            }

            $fieldDefinition = $fieldDefinitions->get($key);

            if ($fieldDefinition) {
                // Use the human-readable name as the key
                $displayName = $fieldDefinition->name;
                $formattedValue = $this->formatCustomFieldForDisplay($fieldDefinition, $value);

                $customFields[$displayName] = [
                    'value' => $formattedValue,
                    'raw_value' => $value,
                    'field_type' => $fieldDefinition->field_type,
                    'key' => $key,
                ];
            } else {
                // Fallback for unknown fields
                $customFields[$key] = [
                    'value' => $value,
                    'raw_value' => $value,
                    'field_type' => 'unknown',
                    'key' => $key,
                ];
            }
        }

        return $customFields;
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
     * Format custom field value for display
     */
    protected function formatCustomFieldForDisplay(PipedriveCustomField $field, $value): string
    {
        if (is_null($value) || $value === '') {
            return 'N/A';
        }

        $customFieldService = app(PipedriveCustomFieldService::class);

        return $customFieldService->formatFieldValue($field, $value);
    }
}
