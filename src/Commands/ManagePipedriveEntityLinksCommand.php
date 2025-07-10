<?php

namespace Keggermont\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Keggermont\LaravelPipedrive\Services\PipedriveEntityLinkService;
use Keggermont\LaravelPipedrive\Models\PipedriveEntityLink;

class ManagePipedriveEntityLinksCommand extends Command
{
    protected $signature = 'pipedrive:entity-links 
                            {action : Action to perform (stats|sync|cleanup|list)}
                            {--entity-type= : Filter by entity type (deals, persons, etc.)}
                            {--model-type= : Filter by Laravel model type}
                            {--status= : Filter by sync status (pending, synced, error)}
                            {--limit=100 : Limit results}
                            {--verbose : Show detailed output}';

    protected $description = 'Manage Pipedrive entity links';

    protected PipedriveEntityLinkService $linkService;

    public function __construct(PipedriveEntityLinkService $linkService)
    {
        parent::__construct();
        $this->linkService = $linkService;
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'stats' => $this->showStats(),
            'sync' => $this->syncLinks(),
            'cleanup' => $this->cleanupLinks(),
            'list' => $this->listLinks(),
            default => $this->error("Unknown action: {$action}. Available actions: stats, sync, cleanup, list"),
        };
    }

    protected function showStats(): int
    {
        $this->info('📊 Pipedrive Entity Links Statistics');
        $this->line('');

        $stats = $this->linkService->getGlobalStats();

        $this->table(['Metric', 'Value'], [
            ['Total Links', $stats['total_links']],
            ['Primary Links', $stats['primary_links']],
        ]);

        $this->line('');
        $this->info('📈 By Entity Type:');
        
        $entityTypeData = [];
        foreach ($stats['by_entity_type'] as $type => $count) {
            $entityTypeData[] = [ucfirst($type), $count];
        }
        
        if (!empty($entityTypeData)) {
            $this->table(['Entity Type', 'Count'], $entityTypeData);
        } else {
            $this->warn('No entity links found.');
        }

        $this->line('');
        $this->info('🔄 By Sync Status:');
        
        $statusData = [];
        foreach ($stats['by_sync_status'] as $status => $count) {
            $statusData[] = [ucfirst($status), $count];
        }
        
        if (!empty($statusData)) {
            $this->table(['Status', 'Count'], $statusData);
        }

        return 0;
    }

    protected function syncLinks(): int
    {
        $entityType = $this->option('entity-type');
        
        if ($entityType) {
            $this->info("🔄 Syncing links for entity type: {$entityType}");
            $results = $this->linkService->syncLinksForEntityType($entityType);
        } else {
            $this->info('🔄 Syncing all entity links...');
            
            $query = PipedriveEntityLink::active();
            
            if ($status = $this->option('status')) {
                $query->where('sync_status', $status);
            }
            
            $results = collect();
            $query->chunk(100, function ($links) use ($results) {
                foreach ($links as $link) {
                    $synced = $this->linkService->syncLink($link);
                    $results->push([
                        'link_id' => $link->id,
                        'entity_type' => $link->pipedrive_entity_type,
                        'entity_id' => $link->pipedrive_entity_id,
                        'synced' => $synced,
                    ]);
                }
            });
        }

        $syncedCount = $results->where('synced', true)->count();
        $failedCount = $results->where('synced', false)->count();

        $this->info("✅ Synced: {$syncedCount}");
        if ($failedCount > 0) {
            $this->warn("❌ Failed: {$failedCount}");
        }

        if ($this->option('verbose')) {
            $this->line('');
            $this->info('📋 Detailed Results:');
            
            $tableData = $results->map(function ($result) {
                return [
                    $result['link_id'],
                    $result['entity_type'],
                    $result['entity_id'],
                    $result['synced'] ? '✅' : '❌',
                ];
            })->toArray();

            $this->table(['Link ID', 'Entity Type', 'Entity ID', 'Status'], $tableData);
        }

        return 0;
    }

    protected function cleanupLinks(): int
    {
        $this->info('🧹 Finding orphaned links...');
        
        $orphaned = $this->linkService->findOrphanedLinks();
        
        if ($orphaned->isEmpty()) {
            $this->info('✅ No orphaned links found.');
            return 0;
        }

        $this->warn("Found {$orphaned->count()} orphaned links.");

        if ($this->confirm('Do you want to deactivate these orphaned links?')) {
            $cleaned = $this->linkService->cleanupOrphanedLinks();
            $this->info("✅ Deactivated {$cleaned} orphaned links.");
        } else {
            $this->info('Cleanup cancelled.');
        }

        return 0;
    }

    protected function listLinks(): int
    {
        $query = PipedriveEntityLink::query();

        // Apply filters
        if ($entityType = $this->option('entity-type')) {
            $query->where('pipedrive_entity_type', $entityType);
        }

        if ($modelType = $this->option('model-type')) {
            $query->where('linkable_type', $modelType);
        }

        if ($status = $this->option('status')) {
            $query->where('sync_status', $status);
        }

        $limit = (int) $this->option('limit');
        $links = $query->with('linkable')->latest()->limit($limit)->get();

        if ($links->isEmpty()) {
            $this->warn('No links found matching the criteria.');
            return 0;
        }

        $this->info("📋 Entity Links (showing {$links->count()} results):");
        $this->line('');

        $tableData = $links->map(function ($link) {
            return [
                $link->id,
                class_basename($link->linkable_type),
                $link->linkable_id,
                $link->pipedrive_entity_type,
                $link->pipedrive_entity_id,
                $link->is_primary ? '⭐' : '',
                $link->sync_status,
                $link->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();

        $this->table([
            'ID', 'Model Type', 'Model ID', 'Entity Type', 
            'Entity ID', 'Primary', 'Status', 'Created'
        ], $tableData);

        if ($this->option('verbose')) {
            $this->line('');
            $this->info('📊 Summary:');
            $this->line("Total links: {$links->count()}");
            $this->line("Primary links: " . $links->where('is_primary', true)->count());
            $this->line("Synced links: " . $links->where('sync_status', 'synced')->count());
            $this->line("Pending links: " . $links->where('sync_status', 'pending')->count());
            $this->line("Error links: " . $links->where('sync_status', 'error')->count());
        }

        return 0;
    }
}
