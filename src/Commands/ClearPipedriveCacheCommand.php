<?php

namespace Keggermont\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Keggermont\LaravelPipedrive\Contracts\PipedriveCacheInterface;

/**
 * Clear Pipedrive Cache Command
 * 
 * Artisan command to clear Pipedrive cache with selective clearing options
 * and verbose output for debugging purposes.
 */
class ClearPipedriveCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pipedrive:cache:clear
                            {--type= : Specific cache type to clear (custom_fields, pipelines, stages, users, field_options)}
                            {--entity= : Specific entity type for custom fields (deal, person, organization, etc.)}
                            {--field= : Specific field key for field options}
                            {--all : Clear all Pipedrive cache}
                            {--stats : Show cache statistics before clearing}';

    /**
     * The console command description.
     */
    protected $description = 'Clear Pipedrive cache data with selective options';

    protected PipedriveCacheInterface $cacheService;

    /**
     * Create a new command instance.
     */
    public function __construct(PipedriveCacheInterface $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->cacheService->isEnabled()) {
            $this->error('Pipedrive cache is disabled in configuration.');
            return self::FAILURE;
        }

        $this->info('🧹 Pipedrive Cache Cleaner');
        $this->newLine();

        // Show statistics if requested
        if ($this->option('stats')) {
            $this->showCacheStatistics();
            $this->newLine();
        }

        // Determine what to clear
        if ($this->option('all')) {
            return $this->clearAllCache();
        }

        $type = $this->option('type');
        $entity = $this->option('entity');
        $field = $this->option('field');

        if (!$type && !$entity && !$field) {
            return $this->interactiveClear();
        }

        return $this->selectiveClear($type, $entity, $field);
    }

    /**
     * Show cache statistics
     */
    protected function showCacheStatistics(): void
    {
        $this->info('📊 Current Cache Statistics:');
        
        $stats = $this->cacheService->getStatistics();
        
        $this->table(
            ['Setting', 'Value'],
            [
                ['Cache Enabled', $stats['enabled'] ? '✅ Yes' : '❌ No'],
                ['Auto Refresh', $stats['auto_refresh'] ? '✅ Yes' : '❌ No'],
                ['Cache Driver', $stats['driver']],
            ]
        );

        // Show cached entities
        if (!empty($stats['cached_entities'])) {
            $this->info('📋 Cached Custom Fields by Entity:');
            $entityRows = [];
            foreach ($stats['cached_entities'] as $entity => $cached) {
                $entityRows[] = [
                    ucfirst($entity),
                    $cached ? '✅ Cached' : '❌ Not Cached'
                ];
            }
            $this->table(['Entity Type', 'Status'], $entityRows);
        }

        // Show other cached data
        if (!empty($stats['cached_data'])) {
            $this->info('🗂️ Other Cached Data:');
            $dataRows = [];
            foreach ($stats['cached_data'] as $type => $cached) {
                $dataRows[] = [
                    ucfirst($type),
                    $cached ? '✅ Cached' : '❌ Not Cached'
                ];
            }
            $this->table(['Data Type', 'Status'], $dataRows);
        }
    }

    /**
     * Clear all cache
     */
    protected function clearAllCache(): int
    {
        $this->info('🗑️ Clearing all Pipedrive cache...');
        
        if ($this->getOutput()->isVerbose()) {
            $this->line('→ Executing clearAll() method...');
        }

        $success = $this->cacheService->clearAll();

        if ($success) {
            $this->info('✅ All Pipedrive cache cleared successfully!');
            return self::SUCCESS;
        } else {
            $this->error('❌ Failed to clear all cache. Check logs for details.');
            return self::FAILURE;
        }
    }

    /**
     * Interactive cache clearing
     */
    protected function interactiveClear(): int
    {
        $this->info('🤔 What would you like to clear?');
        
        $choice = $this->choice(
            'Select cache type to clear:',
            [
                'all' => 'Clear all Pipedrive cache',
                'custom_fields' => 'Clear all custom fields cache',
                'custom_fields_entity' => 'Clear custom fields for specific entity',
                'pipelines' => 'Clear pipelines cache',
                'stages' => 'Clear stages cache',
                'users' => 'Clear users cache',
                'field_options' => 'Clear field options for specific field',
            ],
            'all'
        );

        switch ($choice) {
            case 'all':
                return $this->clearAllCache();
                
            case 'custom_fields':
                return $this->clearCustomFieldsCache();
                
            case 'custom_fields_entity':
                $entity = $this->ask('Enter entity type (deal, person, organization, etc.):');
                return $this->clearEntityCache($entity);
                
            case 'pipelines':
                return $this->clearPipelinesCache();
                
            case 'stages':
                return $this->clearStagesCache();
                
            case 'users':
                return $this->clearUsersCache();
                
            case 'field_options':
                $field = $this->ask('Enter field key:');
                return $this->clearFieldOptionsCache($field);
                
            default:
                $this->error('Invalid choice.');
                return self::FAILURE;
        }
    }

    /**
     * Selective cache clearing based on options
     */
    protected function selectiveClear(?string $type, ?string $entity, ?string $field): int
    {
        if ($entity) {
            return $this->clearEntityCache($entity);
        }

        if ($field) {
            return $this->clearFieldOptionsCache($field);
        }

        return match ($type) {
            'custom_fields' => $this->clearCustomFieldsCache(),
            'pipelines' => $this->clearPipelinesCache(),
            'stages' => $this->clearStagesCache(),
            'users' => $this->clearUsersCache(),
            default => $this->invalidOption($type),
        };
    }

    /**
     * Clear custom fields cache
     */
    protected function clearCustomFieldsCache(): int
    {
        $this->info('🗑️ Clearing all custom fields cache...');
        
        if ($this->getOutput()->isVerbose()) {
            $this->line('→ Executing invalidateCustomFieldsCache() method...');
        }

        $success = $this->cacheService->invalidateCustomFieldsCache();
        
        if ($success) {
            $this->info('✅ Custom fields cache cleared successfully!');
            return self::SUCCESS;
        } else {
            $this->error('❌ Failed to clear custom fields cache. Check logs for details.');
            return self::FAILURE;
        }
    }

    /**
     * Clear entity-specific cache
     */
    protected function clearEntityCache(string $entity): int
    {
        $this->info("🗑️ Clearing cache for entity: {$entity}");
        
        if ($this->getOutput()->isVerbose()) {
            $this->line("→ Executing invalidateEntityCache('{$entity}') method...");
        }

        $success = $this->cacheService->invalidateEntityCache($entity);
        
        if ($success) {
            $this->info("✅ Cache for entity '{$entity}' cleared successfully!");
            return self::SUCCESS;
        } else {
            $this->error("❌ Failed to clear cache for entity '{$entity}'. Check logs for details.");
            return self::FAILURE;
        }
    }

    /**
     * Clear pipelines cache
     */
    protected function clearPipelinesCache(): int
    {
        $this->info('🗑️ Clearing pipelines cache...');
        
        if ($this->getOutput()->isVerbose()) {
            $this->line('→ Executing invalidatePipelinesCache() method...');
        }

        $success = $this->cacheService->invalidatePipelinesCache();
        
        if ($success) {
            $this->info('✅ Pipelines cache cleared successfully!');
            return self::SUCCESS;
        } else {
            $this->error('❌ Failed to clear pipelines cache. Check logs for details.');
            return self::FAILURE;
        }
    }

    /**
     * Clear stages cache
     */
    protected function clearStagesCache(): int
    {
        $this->info('🗑️ Clearing stages cache...');
        
        if ($this->getOutput()->isVerbose()) {
            $this->line('→ Executing invalidateStagesCache() method...');
        }

        $success = $this->cacheService->invalidateStagesCache();
        
        if ($success) {
            $this->info('✅ Stages cache cleared successfully!');
            return self::SUCCESS;
        } else {
            $this->error('❌ Failed to clear stages cache. Check logs for details.');
            return self::FAILURE;
        }
    }

    /**
     * Clear users cache
     */
    protected function clearUsersCache(): int
    {
        $this->info('🗑️ Clearing users cache...');
        
        if ($this->getOutput()->isVerbose()) {
            $this->line('→ Executing invalidateUsersCache() method...');
        }

        $success = $this->cacheService->invalidateUsersCache();
        
        if ($success) {
            $this->info('✅ Users cache cleared successfully!');
            return self::SUCCESS;
        } else {
            $this->error('❌ Failed to clear users cache. Check logs for details.');
            return self::FAILURE;
        }
    }

    /**
     * Clear field options cache
     */
    protected function clearFieldOptionsCache(string $field): int
    {
        $this->info("🗑️ Clearing field options cache for: {$field}");
        
        if ($this->getOutput()->isVerbose()) {
            $this->line("→ Executing invalidateFieldOptionsCache('{$field}') method...");
        }

        $success = $this->cacheService->invalidateFieldOptionsCache($field);
        
        if ($success) {
            $this->info("✅ Field options cache for '{$field}' cleared successfully!");
            return self::SUCCESS;
        } else {
            $this->error("❌ Failed to clear field options cache for '{$field}'. Check logs for details.");
            return self::FAILURE;
        }
    }

    /**
     * Handle invalid option
     */
    protected function invalidOption(?string $type): int
    {
        $this->error("Invalid cache type: {$type}");
        $this->info('Valid types: custom_fields, pipelines, stages, users, field_options');
        return self::FAILURE;
    }
}
