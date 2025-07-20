<?php

namespace Skeylup\LaravelPipedrive\Contracts;

use Illuminate\Support\Collection;

/**
 * Interface for Pipedrive cache operations
 *
 * Provides methods for caching and retrieving Pipedrive entity data
 * with automatic invalidation and TTL management.
 */
interface PipedriveCacheInterface
{
    /**
     * Cache custom fields for a specific entity type
     *
     * @param  string  $entityType  The Pipedrive entity type (deal, person, organization, etc.)
     * @param  Collection  $customFields  Collection of custom fields to cache
     * @param  int|null  $ttl  Time to live in seconds (null uses default from config)
     * @return bool Success status
     */
    public function cacheCustomFields(string $entityType, Collection $customFields, ?int $ttl = null): bool;

    /**
     * Retrieve cached custom fields for an entity type
     *
     * @param  string  $entityType  The Pipedrive entity type
     * @return Collection|null Cached custom fields or null if not found/expired
     */
    public function getCustomFields(string $entityType): ?Collection;

    /**
     * Cache pipelines data
     *
     * @param  Collection  $pipelines  Collection of pipelines to cache
     * @param  int|null  $ttl  Time to live in seconds
     * @return bool Success status
     */
    public function cachePipelines(Collection $pipelines, ?int $ttl = null): bool;

    /**
     * Retrieve cached pipelines
     *
     * @return Collection|null Cached pipelines or null if not found/expired
     */
    public function getPipelines(): ?Collection;

    /**
     * Cache stages data
     *
     * @param  Collection  $stages  Collection of stages to cache
     * @param  int|null  $ttl  Time to live in seconds
     * @return bool Success status
     */
    public function cacheStages(Collection $stages, ?int $ttl = null): bool;

    /**
     * Retrieve cached stages
     *
     * @return Collection|null Cached stages or null if not found/expired
     */
    public function getStages(): ?Collection;

    /**
     * Cache users data
     *
     * @param  Collection  $users  Collection of users to cache
     * @param  int|null  $ttl  Time to live in seconds
     * @return bool Success status
     */
    public function cacheUsers(Collection $users, ?int $ttl = null): bool;

    /**
     * Retrieve cached users
     *
     * @return Collection|null Cached users or null if not found/expired
     */
    public function getUsers(): ?Collection;

    /**
     * Cache enum/set field options for a specific field
     *
     * @param  string  $fieldKey  The custom field key
     * @param  array  $options  Array of field options
     * @param  int|null  $ttl  Time to live in seconds
     * @return bool Success status
     */
    public function cacheFieldOptions(string $fieldKey, array $options, ?int $ttl = null): bool;

    /**
     * Retrieve cached field options
     *
     * @param  string  $fieldKey  The custom field key
     * @return array|null Cached field options or null if not found/expired
     */
    public function getFieldOptions(string $fieldKey): ?array;

    /**
     * Invalidate cache for a specific entity type
     *
     * @param  string  $entityType  The entity type to invalidate
     * @return bool Success status
     */
    public function invalidateEntityCache(string $entityType): bool;

    /**
     * Invalidate all custom fields cache
     *
     * @return bool Success status
     */
    public function invalidateCustomFieldsCache(): bool;

    /**
     * Invalidate pipelines cache
     *
     * @return bool Success status
     */
    public function invalidatePipelinesCache(): bool;

    /**
     * Invalidate stages cache
     *
     * @return bool Success status
     */
    public function invalidateStagesCache(): bool;

    /**
     * Invalidate users cache
     *
     * @return bool Success status
     */
    public function invalidateUsersCache(): bool;

    /**
     * Invalidate field options cache for a specific field
     *
     * @param  string  $fieldKey  The custom field key
     * @return bool Success status
     */
    public function invalidateFieldOptionsCache(string $fieldKey): bool;

    /**
     * Clear all Pipedrive cache
     *
     * @return bool Success status
     */
    public function clearAll(): bool;

    /**
     * Check if cache is enabled
     *
     * @return bool Cache enabled status
     */
    public function isEnabled(): bool;

    /**
     * Get cache statistics
     *
     * @return array Cache statistics including hit/miss ratios, sizes, etc.
     */
    public function getStatistics(): array;

    /**
     * Refresh cache for a specific entity type
     * This will fetch fresh data from Pipedrive API and update cache
     *
     * @param  string  $entityType  The entity type to refresh
     * @return bool Success status
     */
    public function refreshEntityCache(string $entityType): bool;

    /**
     * Check if auto-refresh is enabled
     *
     * @return bool Auto-refresh enabled status
     */
    public function isAutoRefreshEnabled(): bool;
}
