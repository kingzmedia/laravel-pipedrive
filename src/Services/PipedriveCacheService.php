<?php

namespace Keggermont\LaravelPipedrive\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Keggermont\LaravelPipedrive\Contracts\PipedriveCacheInterface;
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;

/**
 * Pipedrive Cache Service
 * 
 * Handles intelligent caching of Pipedrive data with automatic invalidation,
 * configurable TTL, and support for multiple cache drivers.
 */
class PipedriveCacheService implements PipedriveCacheInterface
{
    protected string $cachePrefix = 'pipedrive';
    protected array $config;
    protected PipedriveAuthService $authService;

    public function __construct(PipedriveAuthService $authService)
    {
        $this->authService = $authService;
        $this->config = config('pipedrive.cache', []);
    }

    /**
     * Cache custom fields for a specific entity type
     */
    public function cacheCustomFields(string $entityType, Collection $customFields, ?int $ttl = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $key = $this->getCacheKey('custom_fields', $entityType);
        $ttl = $ttl ?? $this->getTtl('custom_fields');

        try {
            return Cache::put($key, $customFields->toArray(), $ttl);
        } catch (\Exception $e) {
            Log::error("Failed to cache custom fields for {$entityType}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve cached custom fields for an entity type
     */
    public function getCustomFields(string $entityType): ?Collection
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $key = $this->getCacheKey('custom_fields', $entityType);
        
        try {
            $cached = Cache::get($key);
            return $cached ? collect($cached) : null;
        } catch (\Exception $e) {
            Log::error("Failed to retrieve cached custom fields for {$entityType}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cache pipelines data
     */
    public function cachePipelines(Collection $pipelines, ?int $ttl = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $key = $this->getCacheKey('pipelines');
        $ttl = $ttl ?? $this->getTtl('pipelines');

        try {
            return Cache::put($key, $pipelines->toArray(), $ttl);
        } catch (\Exception $e) {
            Log::error("Failed to cache pipelines: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve cached pipelines
     */
    public function getPipelines(): ?Collection
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $key = $this->getCacheKey('pipelines');
        
        try {
            $cached = Cache::get($key);
            return $cached ? collect($cached) : null;
        } catch (\Exception $e) {
            Log::error("Failed to retrieve cached pipelines: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cache stages data
     */
    public function cacheStages(Collection $stages, ?int $ttl = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $key = $this->getCacheKey('stages');
        $ttl = $ttl ?? $this->getTtl('stages');

        try {
            return Cache::put($key, $stages->toArray(), $ttl);
        } catch (\Exception $e) {
            Log::error("Failed to cache stages: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve cached stages
     */
    public function getStages(): ?Collection
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $key = $this->getCacheKey('stages');
        
        try {
            $cached = Cache::get($key);
            return $cached ? collect($cached) : null;
        } catch (\Exception $e) {
            Log::error("Failed to retrieve cached stages: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cache users data
     */
    public function cacheUsers(Collection $users, ?int $ttl = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $key = $this->getCacheKey('users');
        $ttl = $ttl ?? $this->getTtl('users');

        try {
            return Cache::put($key, $users->toArray(), $ttl);
        } catch (\Exception $e) {
            Log::error("Failed to cache users: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve cached users
     */
    public function getUsers(): ?Collection
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $key = $this->getCacheKey('users');
        
        try {
            $cached = Cache::get($key);
            return $cached ? collect($cached) : null;
        } catch (\Exception $e) {
            Log::error("Failed to retrieve cached users: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cache enum/set field options for a specific field
     */
    public function cacheFieldOptions(string $fieldKey, array $options, ?int $ttl = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $key = $this->getCacheKey('field_options', $fieldKey);
        $ttl = $ttl ?? $this->getTtl('custom_fields');

        try {
            return Cache::put($key, $options, $ttl);
        } catch (\Exception $e) {
            Log::error("Failed to cache field options for {$fieldKey}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve cached field options
     */
    public function getFieldOptions(string $fieldKey): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $key = $this->getCacheKey('field_options', $fieldKey);
        
        try {
            return Cache::get($key);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve cached field options for {$fieldKey}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate cache key with prefix
     */
    protected function getCacheKey(string $type, ?string $identifier = null): string
    {
        $key = "{$this->cachePrefix}:{$type}";
        
        if ($identifier) {
            $key .= ":{$identifier}";
        }
        
        return $key;
    }

    /**
     * Get TTL for a specific cache type
     */
    protected function getTtl(string $type): int
    {
        return $this->config['ttl'][$type] ?? 3600; // Default 1 hour
    }

    /**
     * Check if cache is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Check if auto-refresh is enabled
     */
    public function isAutoRefreshEnabled(): bool
    {
        return $this->config['auto_refresh'] ?? true;
    }

    /**
     * Invalidate cache for a specific entity type
     */
    public function invalidateEntityCache(string $entityType): bool
    {
        try {
            $key = $this->getCacheKey('custom_fields', $entityType);
            return Cache::forget($key);
        } catch (\Exception $e) {
            Log::error("Failed to invalidate cache for {$entityType}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Invalidate all custom fields cache
     */
    public function invalidateCustomFieldsCache(): bool
    {
        try {
            $pattern = $this->getCacheKey('custom_fields') . ':*';
            return $this->forgetByPattern($pattern);
        } catch (\Exception $e) {
            Log::error("Failed to invalidate custom fields cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Invalidate pipelines cache
     */
    public function invalidatePipelinesCache(): bool
    {
        try {
            $key = $this->getCacheKey('pipelines');
            return Cache::forget($key);
        } catch (\Exception $e) {
            Log::error("Failed to invalidate pipelines cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Invalidate stages cache
     */
    public function invalidateStagesCache(): bool
    {
        try {
            $key = $this->getCacheKey('stages');
            return Cache::forget($key);
        } catch (\Exception $e) {
            Log::error("Failed to invalidate stages cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Invalidate users cache
     */
    public function invalidateUsersCache(): bool
    {
        try {
            $key = $this->getCacheKey('users');
            return Cache::forget($key);
        } catch (\Exception $e) {
            Log::error("Failed to invalidate users cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Invalidate field options cache for a specific field
     */
    public function invalidateFieldOptionsCache(string $fieldKey): bool
    {
        try {
            $key = $this->getCacheKey('field_options', $fieldKey);
            return Cache::forget($key);
        } catch (\Exception $e) {
            Log::error("Failed to invalidate field options cache for {$fieldKey}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all Pipedrive cache
     */
    public function clearAll(): bool
    {
        try {
            $pattern = $this->cachePrefix . ':*';
            return $this->forgetByPattern($pattern);
        } catch (\Exception $e) {
            Log::error("Failed to clear all Pipedrive cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get cache statistics
     */
    public function getStatistics(): array
    {
        $stats = [
            'enabled' => $this->isEnabled(),
            'auto_refresh' => $this->isAutoRefreshEnabled(),
            'driver' => $this->config['driver'] ?? config('cache.default'),
            'cached_entities' => [],
        ];

        // Check which entities are cached
        $entityTypes = ['deal', 'person', 'organization', 'product', 'activity'];
        foreach ($entityTypes as $entityType) {
            $key = $this->getCacheKey('custom_fields', $entityType);
            $stats['cached_entities'][$entityType] = Cache::has($key);
        }

        // Check other cached data
        $stats['cached_data'] = [
            'pipelines' => Cache::has($this->getCacheKey('pipelines')),
            'stages' => Cache::has($this->getCacheKey('stages')),
            'users' => Cache::has($this->getCacheKey('users')),
        ];

        return $stats;
    }

    /**
     * Refresh cache for a specific entity type
     */
    public function refreshEntityCache(string $entityType): bool
    {
        if (!$this->isEnabled() || !$this->isAutoRefreshEnabled()) {
            return false;
        }

        try {
            // Invalidate current cache
            $this->invalidateEntityCache($entityType);

            // Fetch fresh data from Pipedrive API
            $pipedrive = $this->authService->getPipedriveInstance();
            $customFields = collect($pipedrive->customFields()->all($entityType));

            // Cache the fresh data
            return $this->cacheCustomFields($entityType, $customFields);
        } catch (\Exception $e) {
            Log::error("Failed to refresh cache for {$entityType}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Forget cache entries by pattern (Redis specific)
     */
    protected function forgetByPattern(string $pattern): bool
    {
        $driver = $this->config['driver'] ?? config('cache.default');

        if ($driver === 'redis') {
            try {
                $redis = Cache::getRedis();
                $keys = $redis->keys($pattern);

                if (!empty($keys)) {
                    return $redis->del($keys) > 0;
                }

                return true;
            } catch (\Exception $e) {
                Log::error("Failed to delete Redis keys by pattern {$pattern}: " . $e->getMessage());
                return false;
            }
        }

        // For non-Redis drivers, we need to track keys manually
        // This is a simplified approach - in production you might want to maintain a key registry
        Log::warning("Pattern-based cache clearing not fully supported for driver: {$driver}");
        return true;
    }
}
