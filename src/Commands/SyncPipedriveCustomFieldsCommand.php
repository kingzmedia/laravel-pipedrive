<?php

namespace Keggermont\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Keggermont\LaravelPipedrive\Models\PipedriveCustomField;
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;
use Devio\Pipedrive\Pipedrive;

class SyncPipedriveCustomFieldsCommand extends Command
{
    public $signature = 'pipedrive:sync-custom-fields 
                        {--entity= : Sync fields for specific entity (deal, person, organization, product, activity)}
                        {--force : Force sync even if fields already exist}';

    public $description = 'Synchronize custom fields from Pipedrive API';

    protected Pipedrive $pipedrive;
    protected PipedriveAuthService $authService;

    public function __construct(PipedriveAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    public function handle(): int
    {
        $this->info('Starting Pipedrive custom fields synchronization...');

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
        $force = $this->option('force');

        if ($entityType) {
            if (!in_array($entityType, PipedriveCustomField::getEntityTypes())) {
                $this->error("Invalid entity type. Available types: " . implode(', ', PipedriveCustomField::getEntityTypes()));
                return self::FAILURE;
            }
            
            $this->syncEntityFields($entityType, $force);
        } else {
            // Sync all entity types
            foreach (PipedriveCustomField::getEntityTypes() as $entity) {
                $this->syncEntityFields($entity, $force);
            }
        }

        $this->info('Custom fields synchronization completed!');
        return self::SUCCESS;
    }

    protected function syncEntityFields(string $entityType, bool $force = false): void
    {
        $this->info("Syncing {$entityType} fields...");

        try {
            $fields = $this->getFieldsFromPipedrive($entityType);
            
            if (empty($fields)) {
                $this->warn("No fields found for entity type: {$entityType}");
                return;
            }

            $synced = 0;
            $updated = 0;
            $skipped = 0;

            // Convert objects to arrays if needed
            if (is_object($fields)) {
                $fields = json_decode(json_encode($fields), true);
            }

            foreach ($fields as $fieldData) {
                // Convert individual field data to array if it's an object
                if (is_object($fieldData)) {
                    $fieldData = json_decode(json_encode($fieldData), true);
                }

                // Skip fields without an ID (system/primary fields)
                if (!isset($fieldData['id']) || $fieldData['id'] === null) {
                    $this->warn("  ⚠ Skipped system field: {$fieldData['name']} ({$fieldData['key']})");
                    $skipped++;
                    continue;
                }

                $existingField = PipedriveCustomField::where('pipedrive_field_id', $fieldData['id'])
                    ->where('entity_type', $entityType)
                    ->first();

                if ($existingField && !$force) {
                    $skipped++;
                    continue;
                }

                try {
                    $field = PipedriveCustomField::createOrUpdateFromPipedriveData($fieldData, $entityType);

                    if ($field->wasRecentlyCreated) {
                        $synced++;
                        $this->line("  ✓ Created: {$field->name} ({$field->field_key})");
                    } else {
                        $updated++;
                        $this->line("  ↻ Updated: {$field->name} ({$field->field_key})");
                    }
                } catch (\Exception $e) {
                    $this->error("  ✗ Error processing field {$fieldData['name']}: " . $e->getMessage());
                    continue;
                }
            }

            $this->info("  {$entityType}: {$synced} created, {$updated} updated, {$skipped} skipped");

        } catch (\Exception $e) {
            $this->error("Error syncing {$entityType} fields: " . $e->getMessage());
        }
    }

    protected function getFieldsFromPipedrive(string $entityType): array
    {
        try {
            $response = match ($entityType) {
                PipedriveCustomField::ENTITY_DEAL => $this->pipedrive->dealFields->all(),
                PipedriveCustomField::ENTITY_PERSON => $this->pipedrive->personFields->all(),
                PipedriveCustomField::ENTITY_ORGANIZATION => $this->pipedrive->organizationFields->all(),
                PipedriveCustomField::ENTITY_PRODUCT => $this->pipedrive->productFields->all(),
                PipedriveCustomField::ENTITY_ACTIVITY => $this->pipedrive->activityFields->all(),
                PipedriveCustomField::ENTITY_NOTE => $this->pipedrive->noteFields->all(),
                default => throw new \InvalidArgumentException("Unsupported entity type: {$entityType}"),
            };

            if (!$response->isSuccess()) {
                throw new \Exception("API request failed with status: " . $response->getStatusCode());
            }

            return $response->getData() ?? [];

        } catch (\Exception $e) {
            $this->error("Failed to fetch {$entityType} fields from Pipedrive: " . $e->getMessage());
            return [];
        }
    }
}
