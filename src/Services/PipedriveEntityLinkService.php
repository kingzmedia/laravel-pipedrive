<?php

namespace Skeylup\LaravelPipedrive\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Skeylup\LaravelPipedrive\Models\PipedriveEntityLink;

class PipedriveEntityLinkService
{
    /**
     * Create a link between a Laravel model and a Pipedrive entity
     */
    public function createLink(
        Model $model,
        string $entityType,
        int $entityId,
        bool $isPrimary = false,
        array $metadata = []
    ): PipedriveEntityLink {
        // Validate entity type
        $this->validateEntityType($entityType);

        // If setting as primary, unset other primary links for this model
        if ($isPrimary) {
            PipedriveEntityLink::forModel($model)->update(['is_primary' => false]);
        }

        $link = PipedriveEntityLink::updateOrCreate(
            [
                'linkable_type' => get_class($model),
                'linkable_id' => $model->getKey(),
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
     * Remove a link between a Laravel model and a Pipedrive entity
     */
    public function removeLink(Model $model, string $entityType, int $entityId): bool
    {
        return PipedriveEntityLink::forModel($model)
            ->forEntity($entityType, $entityId)
            ->delete() > 0;
    }

    /**
     * Remove all links for a Laravel model
     */
    public function removeAllLinks(Model $model): int
    {
        return PipedriveEntityLink::forModel($model)->delete();
    }

    /**
     * Get all links for a Laravel model with optimized loading
     */
    public function getLinksForModel(Model $model, bool $withRelations = true): Collection
    {
        $query = PipedriveEntityLink::forModel($model)->active();

        if ($withRelations) {
            $query->with(['linkable', 'pipedriveModel']);
        }

        return $query->get();
    }

    /**
     * Get all models linked to a specific Pipedrive entity with optimized loading
     */
    public function getModelsLinkedToEntity(string $entityType, int $entityId, bool $withPipedriveModel = true): Collection
    {
        $this->validateEntityType($entityType);

        $query = PipedriveEntityLink::forEntity($entityType, $entityId)
            ->active()
            ->with('linkable');

        if ($withPipedriveModel) {
            $query->with('pipedriveModel');
        }

        return $query->get()
            ->pluck('linkable')
            ->filter(); // Remove null values
    }

    /**
     * Sync all links for a specific Pipedrive entity type with optimized chunking
     */
    public function syncLinksForEntityType(string $entityType, int $chunkSize = 100): Collection
    {
        $this->validateEntityType($entityType);

        $results = collect();

        // Use optimized chunking with minimal memory footprint
        PipedriveEntityLink::where('pipedrive_entity_type', $entityType)
            ->active()
            ->select(['id', 'pipedrive_entity_id', 'pipedrive_entity_type']) // Only select needed fields
            ->chunk($chunkSize, function ($links) use ($results) {
                foreach ($links as $link) {
                    $synced = $link->syncLocalPipedriveModel();

                    $results->push([
                        'link_id' => $link->id,
                        'entity_id' => $link->pipedrive_entity_id,
                        'synced' => $synced,
                    ]);

                    if ($synced) {
                        $link->markAsSynced();
                    }
                }
            });

        return $results;
    }

    /**
     * Get statistics about entity links
     */
    public function getGlobalStats(): array
    {
        $totalLinks = PipedriveEntityLink::active()->count();
        $byEntityType = PipedriveEntityLink::active()
            ->selectRaw('pipedrive_entity_type, COUNT(*) as count')
            ->groupBy('pipedrive_entity_type')
            ->pluck('count', 'pipedrive_entity_type');

        $byStatus = PipedriveEntityLink::active()
            ->selectRaw('sync_status, COUNT(*) as count')
            ->groupBy('sync_status')
            ->pluck('count', 'sync_status');

        $primaryLinks = PipedriveEntityLink::active()->primary()->count();

        return [
            'total_links' => $totalLinks,
            'by_entity_type' => $byEntityType,
            'by_sync_status' => $byStatus,
            'primary_links' => $primaryLinks,
        ];
    }

    /**
     * Find orphaned links (where the local Pipedrive model doesn't exist)
     */
    public function findOrphanedLinks(): Collection
    {
        $orphaned = collect();

        PipedriveEntityLink::active()->chunk(100, function ($links) use ($orphaned) {
            foreach ($links as $link) {
                $localModel = $link->getLocalPipedriveModel();
                if (! $localModel) {
                    $orphaned->push($link);
                }
            }
        });

        return $orphaned;
    }

    /**
     * Clean up orphaned links
     */
    public function cleanupOrphanedLinks(): int
    {
        $orphaned = $this->findOrphanedLinks();
        $count = 0;

        foreach ($orphaned as $link) {
            $link->update(['is_active' => false]);
            $count++;
        }

        return $count;
    }

    /**
     * Bulk create links from an array
     */
    public function bulkCreateLinks(array $linksData): Collection
    {
        $results = collect();

        foreach ($linksData as $linkData) {
            try {
                $model = $linkData['model'];
                $entityType = $linkData['entity_type'];
                $entityId = $linkData['entity_id'];
                $isPrimary = $linkData['is_primary'] ?? false;
                $metadata = $linkData['metadata'] ?? [];

                $link = $this->createLink($model, $entityType, $entityId, $isPrimary, $metadata);

                $results->push([
                    'success' => true,
                    'link' => $link,
                    'model_type' => get_class($model),
                    'model_id' => $model->getKey(),
                ]);
            } catch (\Exception $e) {
                $results->push([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => $linkData,
                ]);
            }
        }

        return $results;
    }

    /**
     * Validate entity type
     */
    protected function validateEntityType(string $entityType): void
    {
        $validTypes = [
            'deals', 'persons', 'organizations', 'activities',
            'products', 'files', 'notes', 'users', 'pipelines',
            'stages', 'goals',
        ];

        if (! in_array($entityType, $validTypes)) {
            throw new \InvalidArgumentException("Invalid entity type: {$entityType}. Valid types are: ".implode(', ', $validTypes));
        }
    }

    /**
     * Get the model class for a Pipedrive entity type
     */
    public function getModelClassForEntityType(string $entityType): ?string
    {
        $entityMap = [
            'deals' => \Skeylup\LaravelPipedrive\Models\PipedriveDeal::class,
            'persons' => \Skeylup\LaravelPipedrive\Models\PipedrivePerson::class,
            'organizations' => \Skeylup\LaravelPipedrive\Models\PipedriveOrganization::class,
            'activities' => \Skeylup\LaravelPipedrive\Models\PipedriveActivity::class,
            'products' => \Skeylup\LaravelPipedrive\Models\PipedriveProduct::class,
            'files' => \Skeylup\LaravelPipedrive\Models\PipedriveFile::class,
            'notes' => \Skeylup\LaravelPipedrive\Models\PipedriveNote::class,
            'users' => \Skeylup\LaravelPipedrive\Models\PipedriveUser::class,
            'pipelines' => \Skeylup\LaravelPipedrive\Models\PipedrivePipeline::class,
            'stages' => \Skeylup\LaravelPipedrive\Models\PipedriveStage::class,
            'goals' => \Skeylup\LaravelPipedrive\Models\PipedriveGoal::class,
        ];

        return $entityMap[$entityType] ?? null;
    }

    /**
     * Sync a specific link
     */
    public function syncLink(PipedriveEntityLink $link): bool
    {
        try {
            $synced = $link->syncLocalPipedriveModel();

            if ($synced) {
                $link->markAsSynced();
            } else {
                $link->markAsError('Local Pipedrive model not found');
            }

            return $synced;
        } catch (\Exception $e) {
            $link->markAsError($e->getMessage());

            return false;
        }
    }
}
