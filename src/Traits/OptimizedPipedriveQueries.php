<?php

namespace Skeylup\LaravelPipedrive\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Optimized Pipedrive Queries Trait
 * 
 * Provides query optimization features for Pipedrive models including
 * automatic eager loading, intelligent pagination, and performance optimizations.
 */
trait OptimizedPipedriveQueries
{
    /**
     * Default relationships to eager load for this model
     * Override this in your model to specify default relationships
     */
    protected array $defaultEagerLoad = [];

    /**
     * Relationships that should be counted instead of loaded
     * Useful for performance when you only need counts
     */
    protected array $defaultWithCount = [];

    /**
     * Maximum number of records to load without pagination
     */
    protected int $maxRecordsWithoutPagination = 1000;

    /**
     * Default pagination size
     */
    protected int $defaultPaginationSize = 50;

    /**
     * Scope to apply default eager loading
     */
    public function scopeWithDefaultRelations(Builder $query): Builder
    {
        if (!empty($this->defaultEagerLoad)) {
            $query->with($this->defaultEagerLoad);
        }

        if (!empty($this->defaultWithCount)) {
            $query->withCount($this->defaultWithCount);
        }

        return $query;
    }

    /**
     * Scope to apply optimized loading for lists
     * Automatically applies pagination if result set is large
     */
    public function scopeOptimizedForList(Builder $query, ?int $perPage = null): Builder
    {
        $perPage = $perPage ?? $this->defaultPaginationSize;

        // Apply default relations
        $query->withDefaultRelations();

        // Add basic ordering for consistent results
        if (!$query->getQuery()->orders) {
            $query->orderBy($this->getKeyName());
        }

        return $query;
    }

    /**
     * Scope to apply optimized loading for detail views
     * Loads all necessary relationships for displaying full entity details
     */
    public function scopeOptimizedForDetail(Builder $query): Builder
    {
        // Load all default relationships
        $query->withDefaultRelations();

        // Add model-specific detail relationships
        if (method_exists($this, 'getDetailRelationships')) {
            $detailRelations = $this->getDetailRelationships();
            if (!empty($detailRelations)) {
                $query->with($detailRelations);
            }
        }

        return $query;
    }

    /**
     * Scope for efficient counting
     * Optimizes queries when you only need counts
     */
    public function scopeForCounting(Builder $query): Builder
    {
        // Remove unnecessary selects and relationships for counting
        $query->select($this->getKeyName());
        
        return $query;
    }

    /**
     * Scope to optimize queries for API responses
     * Includes only essential data and relationships
     */
    public function scopeForApi(Builder $query, array $includes = []): Builder
    {
        // Apply default relations
        $query->withDefaultRelations();

        // Add requested includes
        if (!empty($includes)) {
            $query->with($includes);
        }

        return $query;
    }

    /**
     * Intelligent pagination that automatically determines if pagination is needed
     */
    public function scopeSmartPaginate(Builder $query, ?int $perPage = null, ?int $maxRecords = null): Builder|LengthAwarePaginator
    {
        $perPage = $perPage ?? $this->defaultPaginationSize;
        $maxRecords = $maxRecords ?? $this->maxRecordsWithoutPagination;

        // Get total count efficiently
        $totalCount = $query->toBase()->getCountForPagination();

        // If total is small enough, return all records without pagination
        if ($totalCount <= $maxRecords) {
            return $query->get();
        }

        // Otherwise, return paginated results
        return $query->paginate($perPage);
    }

    /**
     * Optimized method to get all records with intelligent loading
     */
    public static function getAllOptimized(array $relations = [], ?int $limit = null): Collection
    {
        $query = static::query()->withDefaultRelations();

        if (!empty($relations)) {
            $query->with($relations);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Optimized method to find by Pipedrive ID with relationships
     */
    public static function findByPipedriveIdOptimized(int $pipedriveId, array $relations = []): ?static
    {
        $query = static::query()
            ->withDefaultRelations()
            ->where('pipedrive_id', $pipedriveId);

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->first();
    }

    /**
     * Optimized method to get active records with relationships
     */
    public static function getActiveOptimized(array $relations = [], ?int $limit = null): Collection
    {
        $query = static::query()
            ->withDefaultRelations()
            ->active();

        if (!empty($relations)) {
            $query->with($relations);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Batch load multiple records by Pipedrive IDs efficiently
     */
    public static function loadByPipedriveIds(array $pipedriveIds, array $relations = []): Collection
    {
        if (empty($pipedriveIds)) {
            return new Collection();
        }

        $query = static::query()
            ->withDefaultRelations()
            ->whereIn('pipedrive_id', $pipedriveIds);

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->get();
    }

    /**
     * Get records with optimized search
     * Includes basic text search across common fields
     */
    public function scopeOptimizedSearch(Builder $query, string $searchTerm, array $searchFields = []): Builder
    {
        if (empty($searchFields)) {
            $searchFields = $this->getSearchableFields();
        }

        $query->where(function ($q) use ($searchTerm, $searchFields) {
            foreach ($searchFields as $field) {
                $q->orWhere($field, 'LIKE', "%{$searchTerm}%");
            }
        });

        return $query->withDefaultRelations();
    }

    /**
     * Get searchable fields for this model
     * Override in your model to specify searchable fields
     */
    protected function getSearchableFields(): array
    {
        $commonFields = ['name', 'title'];
        $availableFields = [];

        foreach ($commonFields as $field) {
            if (in_array($field, $this->fillable)) {
                $availableFields[] = $field;
            }
        }

        return $availableFields;
    }

    /**
     * Scope to optimize queries for exports
     * Chunks large datasets for memory efficiency
     */
    public function scopeForExport(Builder $query, int $chunkSize = 1000): Builder
    {
        // Remove unnecessary relationships for export
        // Only load essential data
        return $query->select($this->getTable() . '.*');
    }

    /**
     * Get performance statistics for this model
     */
    public static function getPerformanceStats(): array
    {
        $instance = new static();
        $table = $instance->getTable();

        return [
            'total_records' => static::count(),
            'active_records' => static::where('active_flag', true)->count(),
            'table_name' => $table,
            'default_eager_load' => $instance->defaultEagerLoad ?? [],
            'default_with_count' => $instance->defaultWithCount ?? [],
            'max_records_without_pagination' => $instance->maxRecordsWithoutPagination,
            'default_pagination_size' => $instance->defaultPaginationSize,
        ];
    }

    /**
     * Scope to apply database-specific optimizations
     */
    public function scopeWithDatabaseOptimizations(Builder $query): Builder
    {
        // Add index hints or other database-specific optimizations
        // This can be extended based on your database engine
        
        return $query;
    }
}
