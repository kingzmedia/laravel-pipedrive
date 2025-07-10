<?php

namespace Keggermont\LaravelPipedrive\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Keggermont\LaravelPipedrive\Contracts\PipedriveCacheInterface;

/**
 * Pipedrive Query Optimization Service
 * 
 * Provides intelligent query optimization, pagination, and performance monitoring
 * for Pipedrive entities with automatic caching integration.
 */
class PipedriveQueryOptimizationService
{
    protected PipedriveCacheInterface $cacheService;
    
    /**
     * Default pagination settings
     */
    protected array $paginationDefaults = [
        'per_page' => 50,
        'max_per_page' => 500,
        'auto_paginate_threshold' => 1000,
    ];

    /**
     * Query performance thresholds (in milliseconds)
     */
    protected array $performanceThresholds = [
        'slow_query' => 1000,
        'very_slow_query' => 5000,
    ];

    public function __construct(PipedriveCacheInterface $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Optimize a query builder with intelligent pagination and caching
     */
    public function optimizeQuery(
        Builder $query,
        array $options = []
    ): Builder|LengthAwarePaginator|Collection {
        $startTime = microtime(true);
        
        // Extract options
        $perPage = $options['per_page'] ?? $this->paginationDefaults['per_page'];
        $maxPerPage = $options['max_per_page'] ?? $this->paginationDefaults['max_per_page'];
        $forceCollection = $options['force_collection'] ?? false;
        $enableCache = $options['enable_cache'] ?? true;
        $cacheKey = $options['cache_key'] ?? null;
        $cacheTtl = $options['cache_ttl'] ?? null;

        // Validate pagination parameters
        $perPage = min($perPage, $maxPerPage);

        // Try to get from cache first
        if ($enableCache && $cacheKey && $this->cacheService->isEnabled()) {
            $cached = $this->getCachedResult($cacheKey);
            if ($cached !== null) {
                $this->logQueryPerformance('cache_hit', $startTime, $cacheKey);
                return $cached;
            }
        }

        // Determine if we should paginate
        if (!$forceCollection) {
            $totalCount = $this->getOptimizedCount($query);
            
            if ($totalCount > $this->paginationDefaults['auto_paginate_threshold']) {
                $result = $this->createOptimizedPagination($query, $perPage, $totalCount);
            } else {
                $result = $this->getOptimizedCollection($query);
            }
        } else {
            $result = $this->getOptimizedCollection($query);
        }

        // Cache the result if enabled
        if ($enableCache && $cacheKey && $this->cacheService->isEnabled()) {
            $this->cacheResult($cacheKey, $result, $cacheTtl);
        }

        $this->logQueryPerformance('query_executed', $startTime, $cacheKey);
        
        return $result;
    }

    /**
     * Get optimized count for a query
     */
    protected function getOptimizedCount(Builder $query): int
    {
        // Clone the query to avoid modifying the original
        $countQuery = clone $query;
        
        // Remove unnecessary parts for counting
        $countQuery->getQuery()->orders = null;
        $countQuery->getQuery()->limit = null;
        $countQuery->getQuery()->offset = null;
        
        return $countQuery->count();
    }

    /**
     * Create optimized pagination
     */
    protected function createOptimizedPagination(Builder $query, int $perPage, int $totalCount): LengthAwarePaginator
    {
        // Apply default ordering if none exists
        if (empty($query->getQuery()->orders)) {
            $query->orderBy($query->getModel()->getKeyName());
        }

        return $query->paginate($perPage);
    }

    /**
     * Get optimized collection
     */
    protected function getOptimizedCollection(Builder $query): Collection
    {
        // Apply default ordering if none exists
        if (empty($query->getQuery()->orders)) {
            $query->orderBy($query->getModel()->getKeyName());
        }

        return $query->get();
    }

    /**
     * Optimize queries for bulk operations
     */
    public function optimizeBulkQuery(Builder $query, int $chunkSize = 1000): \Generator
    {
        // Ensure we have ordering for consistent chunking
        if (empty($query->getQuery()->orders)) {
            $query->orderBy($query->getModel()->getKeyName());
        }

        // Use lazy collection for memory efficiency
        return $query->lazy($chunkSize);
    }

    /**
     * Optimize queries for exports
     */
    public function optimizeForExport(
        Builder $query,
        array $selectFields = [],
        int $chunkSize = 1000
    ): \Generator {
        // Select only necessary fields to reduce memory usage
        if (!empty($selectFields)) {
            $query->select($selectFields);
        }

        // Remove unnecessary relationships for export
        $query->without($query->getEagerLoads());

        // Use chunking for large datasets
        foreach ($query->lazy($chunkSize) as $record) {
            yield $record;
        }
    }

    /**
     * Get cached result
     */
    protected function getCachedResult(string $cacheKey)
    {
        try {
            return cache()->get($cacheKey);
        } catch (\Exception $e) {
            Log::warning("Failed to retrieve cached result for key: {$cacheKey}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Cache query result
     */
    protected function cacheResult(string $cacheKey, $result, ?int $ttl = null): void
    {
        try {
            $ttl = $ttl ?? 3600; // Default 1 hour
            cache()->put($cacheKey, $result, $ttl);
        } catch (\Exception $e) {
            Log::warning("Failed to cache result for key: {$cacheKey}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log query performance
     */
    protected function logQueryPerformance(string $type, float $startTime, ?string $cacheKey = null): void
    {
        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        
        $logData = [
            'type' => $type,
            'execution_time_ms' => round($executionTime, 2),
            'cache_key' => $cacheKey,
        ];

        if ($executionTime > $this->performanceThresholds['very_slow_query']) {
            Log::warning('Very slow Pipedrive query detected', $logData);
        } elseif ($executionTime > $this->performanceThresholds['slow_query']) {
            Log::info('Slow Pipedrive query detected', $logData);
        } else {
            Log::debug('Pipedrive query performance', $logData);
        }
    }

    /**
     * Analyze query performance
     */
    public function analyzeQueryPerformance(Builder $query): array
    {
        $startTime = microtime(true);
        
        // Get query SQL and bindings
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        
        // Execute EXPLAIN ANALYZE if using PostgreSQL
        $explainResult = null;
        if (DB::getDriverName() === 'pgsql') {
            try {
                $explainResult = DB::select("EXPLAIN ANALYZE " . $sql, $bindings);
            } catch (\Exception $e) {
                Log::warning('Failed to execute EXPLAIN ANALYZE', ['error' => $e->getMessage()]);
            }
        }

        // Get basic count
        $count = $this->getOptimizedCount($query);
        
        $executionTime = (microtime(true) - $startTime) * 1000;

        return [
            'sql' => $sql,
            'bindings' => $bindings,
            'count' => $count,
            'execution_time_ms' => round($executionTime, 2),
            'explain_result' => $explainResult,
            'performance_rating' => $this->getPerformanceRating($executionTime),
            'recommendations' => $this->getPerformanceRecommendations($executionTime, $count),
        ];
    }

    /**
     * Get performance rating based on execution time
     */
    protected function getPerformanceRating(float $executionTimeMs): string
    {
        if ($executionTimeMs < 100) {
            return 'excellent';
        } elseif ($executionTimeMs < 500) {
            return 'good';
        } elseif ($executionTimeMs < 1000) {
            return 'fair';
        } elseif ($executionTimeMs < 5000) {
            return 'poor';
        } else {
            return 'very_poor';
        }
    }

    /**
     * Get performance recommendations
     */
    protected function getPerformanceRecommendations(float $executionTimeMs, int $count): array
    {
        $recommendations = [];

        if ($executionTimeMs > $this->performanceThresholds['slow_query']) {
            $recommendations[] = 'Consider adding database indexes for this query';
            $recommendations[] = 'Enable caching for frequently executed queries';
        }

        if ($count > 10000) {
            $recommendations[] = 'Use pagination for large result sets';
            $recommendations[] = 'Consider using lazy loading for bulk operations';
        }

        if ($count > 1000 && $executionTimeMs > 500) {
            $recommendations[] = 'Optimize eager loading relationships';
            $recommendations[] = 'Consider using select() to limit returned columns';
        }

        return $recommendations;
    }

    /**
     * Get query optimization statistics
     */
    public function getOptimizationStatistics(): array
    {
        return [
            'pagination_defaults' => $this->paginationDefaults,
            'performance_thresholds' => $this->performanceThresholds,
            'cache_enabled' => $this->cacheService->isEnabled(),
            'cache_statistics' => $this->cacheService->getStatistics(),
        ];
    }

    /**
     * Update pagination defaults
     */
    public function updatePaginationDefaults(array $defaults): void
    {
        $this->paginationDefaults = array_merge($this->paginationDefaults, $defaults);
    }

    /**
     * Update performance thresholds
     */
    public function updatePerformanceThresholds(array $thresholds): void
    {
        $this->performanceThresholds = array_merge($this->performanceThresholds, $thresholds);
    }
}
