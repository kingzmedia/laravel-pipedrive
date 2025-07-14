<?php

namespace Keggermont\LaravelPipedrive\Services;

use Illuminate\Support\Facades\Config;
use Keggermont\LaravelPipedrive\Enums\PipedriveEntityType;

/**
 * Service to manage Pipedrive entity configuration
 * 
 * Handles which entities are enabled for synchronization based on configuration
 */
class PipedriveEntityConfigService
{
    /**
     * All available Pipedrive entities
     */
    protected array $allEntities = [
        'activities', 'deals', 'files', 'goals', 'notes', 'organizations',
        'persons', 'pipelines', 'products', 'stages', 'users'
    ];

    /**
     * Get enabled entities for synchronization
     * 
     * @return array List of enabled entity types
     */
    public function getEnabledEntities(): array
    {
        $configuredEntities = Config::get('pipedrive.sync.enabled_entities', []);
        
        // If empty or contains "all", return all entities
        if (empty($configuredEntities) || in_array('all', $configuredEntities)) {
            return $this->allEntities;
        }

        // Filter and validate configured entities
        $enabledEntities = [];
        foreach ($configuredEntities as $entity) {
            $entity = trim($entity);
            if ($this->isValidEntity($entity)) {
                $enabledEntities[] = $entity;
            }
        }

        return $enabledEntities;
    }

    /**
     * Check if a specific entity is enabled
     * 
     * @param string $entityType The entity type to check
     * @return bool True if the entity is enabled
     */
    public function isEntityEnabled(string $entityType): bool
    {
        return in_array($entityType, $this->getEnabledEntities());
    }

    /**
     * Get all available entities
     * 
     * @return array List of all available entity types
     */
    public function getAllEntities(): array
    {
        return $this->allEntities;
    }

    /**
     * Validate if an entity type is valid
     * 
     * @param string $entityType The entity type to validate
     * @return bool True if valid
     */
    public function isValidEntity(string $entityType): bool
    {
        return in_array($entityType, $this->allEntities);
    }

    /**
     * Get disabled entities (all entities minus enabled ones)
     * 
     * @return array List of disabled entity types
     */
    public function getDisabledEntities(): array
    {
        $enabled = $this->getEnabledEntities();
        return array_diff($this->allEntities, $enabled);
    }

    /**
     * Get entity configuration summary
     * 
     * @return array Configuration summary with enabled/disabled entities
     */
    public function getConfigurationSummary(): array
    {
        $enabled = $this->getEnabledEntities();
        $disabled = $this->getDisabledEntities();

        return [
            'total_entities' => count($this->allEntities),
            'enabled_count' => count($enabled),
            'disabled_count' => count($disabled),
            'enabled_entities' => $enabled,
            'disabled_entities' => $disabled,
            'configuration_source' => Config::get('pipedrive.sync.enabled_entities') ? 'PIPEDRIVE_ENABLED_ENTITIES' : 'default',
        ];
    }

    /**
     * Filter entities list based on enabled configuration
     * 
     * @param array $entities List of entities to filter
     * @return array Filtered list containing only enabled entities
     */
    public function filterEnabledEntities(array $entities): array
    {
        $enabled = $this->getEnabledEntities();
        return array_intersect($entities, $enabled);
    }

    /**
     * Get entities with their display names (only enabled ones)
     * 
     * @return array Associative array with entity => display name
     */
    public function getEnabledEntitiesWithNames(): array
    {
        $enabled = $this->getEnabledEntities();
        $result = [];

        foreach ($enabled as $entity) {
            try {
                $entityType = PipedriveEntityType::fromString($entity);
                $result[$entity] = $entityType->getDisplayName();
            } catch (\InvalidArgumentException $e) {
                // Skip invalid entities
                continue;
            }
        }

        return $result;
    }

    /**
     * Validate configuration and return any issues
     * 
     * @return array List of configuration issues/warnings
     */
    public function validateConfiguration(): array
    {
        $issues = [];
        $configuredEntities = Config::get('pipedrive.sync.enabled_entities', []);

        if (empty($configuredEntities)) {
            $issues[] = [
                'type' => 'info',
                'message' => 'No entities configured, all entities will be synchronized by default.'
            ];
        }

        foreach ($configuredEntities as $entity) {
            $entity = trim($entity);
            if (!$this->isValidEntity($entity) && $entity !== 'all') {
                $issues[] = [
                    'type' => 'warning',
                    'message' => "Invalid entity '{$entity}' in configuration. Available entities: " . implode(', ', $this->allEntities)
                ];
            }
        }

        return $issues;
    }
}
