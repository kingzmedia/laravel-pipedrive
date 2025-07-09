<?php

namespace Keggermont\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;
use Keggermont\LaravelPipedrive\Models\PipedriveActivity;
use Keggermont\LaravelPipedrive\Models\PipedriveDeal;
use Keggermont\LaravelPipedrive\Models\PipedriveFile;
use Keggermont\LaravelPipedrive\Models\PipedriveGoal;
use Keggermont\LaravelPipedrive\Models\PipedriveNote;
use Keggermont\LaravelPipedrive\Models\PipedriveOrganization;
use Keggermont\LaravelPipedrive\Models\PipedrivePerson;
use Keggermont\LaravelPipedrive\Models\PipedrivePipeline;
use Keggermont\LaravelPipedrive\Models\PipedriveProduct;
use Keggermont\LaravelPipedrive\Models\PipedriveStage;
use Keggermont\LaravelPipedrive\Models\PipedriveUser;
use Devio\Pipedrive\Pipedrive;

class SyncPipedriveEntitiesCommand extends Command
{
    public $signature = 'pipedrive:sync-entities 
                        {--entity= : Sync specific entity (activities, deals, files, goals, notes, organizations, persons, pipelines, products, stages, users)}
                        {--limit=100 : Limit number of records to sync per entity}
                        {--force : Force sync even if records already exist}';

    public $description = 'Synchronize entities from Pipedrive API';

    protected Pipedrive $pipedrive;
    protected PipedriveAuthService $authService;

    /**
     * Available entities and their model classes
     */
    protected array $entities = [
        'activities' => PipedriveActivity::class,
        'deals' => PipedriveDeal::class,
        'files' => PipedriveFile::class,
        'goals' => PipedriveGoal::class,
        'notes' => PipedriveNote::class,
        'organizations' => PipedriveOrganization::class,
        'persons' => PipedrivePerson::class,
        'pipelines' => PipedrivePipeline::class,
        'products' => PipedriveProduct::class,
        'stages' => PipedriveStage::class,
        'users' => PipedriveUser::class,
    ];

    public function __construct(PipedriveAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    public function handle(): int
    {
        $this->info('Starting Pipedrive entities synchronization...');

        // Initialize Pipedrive client
        try {
            $this->pipedrive = $this->authService->getPipedriveInstance();
            
            // Test connection
            $connectionTest = $this->authService->testConnection();
            if (!$connectionTest['success']) {
                $this->error('Failed to connect to Pipedrive: ' . $connectionTest['message']);
                return self::FAILURE;
            }
            
            $this->info('Connected to Pipedrive as: ' . $connectionTest['user'] . ' (' . $connectionTest['company'] . ')');
            $this->info('Using authentication method: ' . $this->authService->getAuthMethod());
            
        } catch (\Exception $e) {
            $this->error('Error initializing Pipedrive client: ' . $e->getMessage());
            return self::FAILURE;
        }

        $entityType = $this->option('entity');
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        if ($entityType) {
            if (!array_key_exists($entityType, $this->entities)) {
                $this->error("Invalid entity type. Available types: " . implode(', ', array_keys($this->entities)));
                return self::FAILURE;
            }
            
            $this->syncEntity($entityType, $limit, $force);
        } else {
            // Sync all entities
            foreach (array_keys($this->entities) as $entity) {
                $this->syncEntity($entity, $limit, $force);
            }
        }

        $this->info('Entities synchronization completed!');
        return self::SUCCESS;
    }

    protected function syncEntity(string $entityType, int $limit, bool $force = false): void
    {
        $this->info("Syncing {$entityType}...");

        try {
            $modelClass = $this->entities[$entityType];
            $data = $this->getDataFromPipedrive($entityType, $limit);
            
            if (empty($data)) {
                $this->warn("No data found for entity type: {$entityType}");
                return;
            }

            $synced = 0;
            $updated = 0;
            $skipped = 0;
            $errors = 0;

            // Convert objects to arrays if needed
            if (is_object($data)) {
                $data = json_decode(json_encode($data), true);
            }

            foreach ($data as $itemData) {
                // Convert individual item data to array if it's an object
                if (is_object($itemData)) {
                    $itemData = json_decode(json_encode($itemData), true);
                }

                // Skip items without an ID
                if (!isset($itemData['id']) || $itemData['id'] === null) {
                    $this->warn("  ⚠ Skipped item without ID in {$entityType}");
                    $skipped++;
                    continue;
                }

                $existingRecord = $modelClass::where('pipedrive_id', $itemData['id'])->first();

                if ($existingRecord && !$force) {
                    $skipped++;
                    continue;
                }

                try {
                    $record = $modelClass::createOrUpdateFromPipedriveData($itemData);
                    
                    if ($record->wasRecentlyCreated) {
                        $synced++;
                        $this->line("  ✓ Created: {$this->getRecordDisplayName($record, $itemData)}");
                    } else {
                        $updated++;
                        $this->line("  ↻ Updated: {$this->getRecordDisplayName($record, $itemData)}");
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("  ✗ Error processing {$entityType} item {$itemData['id']}: " . $e->getMessage());
                    continue;
                }
            }

            $this->info("  {$entityType}: {$synced} created, {$updated} updated, {$skipped} skipped, {$errors} errors");

        } catch (\Exception $e) {
            $this->error("Error syncing {$entityType}: " . $e->getMessage());
        }
    }

    protected function getDataFromPipedrive(string $entityType, int $limit): array
    {
        try {
            $response = match ($entityType) {
                'activities' => $this->pipedrive->activities->all(['limit' => $limit]),
                'deals' => $this->pipedrive->deals->all(['limit' => $limit]),
                'files' => $this->pipedrive->files->all(['limit' => $limit]),
                'goals' => $this->pipedrive->goals->all(['limit' => $limit]),
                'notes' => $this->pipedrive->notes->all(['limit' => $limit]),
                'organizations' => $this->pipedrive->organizations->all(['limit' => $limit]),
                'persons' => $this->pipedrive->persons->all(['limit' => $limit]),
                'pipelines' => $this->pipedrive->pipelines->all(),
                'products' => $this->pipedrive->products->all(['limit' => $limit]),
                'stages' => $this->pipedrive->stages->all(),
                'users' => $this->pipedrive->users->all(),
                default => throw new \InvalidArgumentException("Unsupported entity type: {$entityType}"),
            };

            if (!$response->isSuccess()) {
                throw new \Exception("API request failed with status: " . $response->getStatusCode());
            }

            return $response->getData() ?? [];

        } catch (\Exception $e) {
            $this->error("Failed to fetch {$entityType} from Pipedrive: " . $e->getMessage());
            return [];
        }
    }

    protected function getRecordDisplayName($record, array $data): string
    {
        // Try to get a meaningful display name for the record
        if (isset($data['title'])) {
            return $data['title'] . " (ID: {$data['id']})";
        }
        
        if (isset($data['name'])) {
            return $data['name'] . " (ID: {$data['id']})";
        }
        
        if (isset($data['subject'])) {
            return $data['subject'] . " (ID: {$data['id']})";
        }
        
        return "ID: {$data['id']}";
    }
}
