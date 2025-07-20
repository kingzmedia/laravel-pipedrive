<?php

namespace Skeylup\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Skeylup\LaravelPipedrive\Services\PipedriveEntityConfigService;

class ShowPipedriveConfigCommand extends Command
{
    public $signature = 'pipedrive:config
                        {--entities : Show only entity configuration}
                        {--json : Output in JSON format}';

    public $description = 'Display current Pipedrive configuration including enabled/disabled entities';

    protected PipedriveEntityConfigService $entityConfigService;

    public function __construct(PipedriveEntityConfigService $entityConfigService)
    {
        parent::__construct();
        $this->entityConfigService = $entityConfigService;
    }

    public function handle(): int
    {
        $showEntitiesOnly = $this->option('entities');
        $jsonOutput = $this->option('json');

        if ($showEntitiesOnly) {
            return $this->showEntityConfiguration($jsonOutput);
        }

        return $this->showFullConfiguration($jsonOutput);
    }

    protected function showEntityConfiguration(bool $jsonOutput): int
    {
        $configSummary = $this->entityConfigService->getConfigurationSummary();
        $issues = $this->entityConfigService->validateConfiguration();

        if ($jsonOutput) {
            $this->line(json_encode([
                'entity_configuration' => $configSummary,
                'validation_issues' => $issues,
            ], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('📋 Pipedrive Entity Configuration');
        $this->line('');

        // Summary
        $this->line('📊 Summary:');
        $this->line("  → Total entities: {$configSummary['total_entities']}");
        $this->line("  → Enabled: {$configSummary['enabled_count']}");
        $this->line("  → Disabled: {$configSummary['disabled_count']}");
        $this->line("  → Configuration source: {$configSummary['configuration_source']}");
        $this->line('');

        // Enabled entities
        if (! empty($configSummary['enabled_entities'])) {
            $this->line("✅ Enabled entities ({$configSummary['enabled_count']}):");
            foreach ($configSummary['enabled_entities'] as $entity) {
                $this->line("  → {$entity}");
            }
            $this->line('');
        }

        // Disabled entities
        if (! empty($configSummary['disabled_entities'])) {
            $this->line("❌ Disabled entities ({$configSummary['disabled_count']}):");
            foreach ($configSummary['disabled_entities'] as $entity) {
                $this->line("  → {$entity}");
            }
            $this->line('');
        }

        // Configuration instructions
        $this->line('⚙️  Configuration:');
        $this->line('  → To enable/disable entities, set PIPEDRIVE_ENABLED_ENTITIES in your .env file');
        $this->line('  → Format: PIPEDRIVE_ENABLED_ENTITIES=deals,activities,persons');
        $this->line("  → Use 'all' to enable all entities: PIPEDRIVE_ENABLED_ENTITIES=all");
        $this->line('');

        // Validation issues
        if (! empty($issues)) {
            $this->line('⚠️  Configuration Issues:');
            foreach ($issues as $issue) {
                $icon = $issue['type'] === 'warning' ? '⚠️ ' : 'ℹ️ ';
                $this->line("  {$icon} {$issue['message']}");
            }
        }

        return self::SUCCESS;
    }

    protected function showFullConfiguration(bool $jsonOutput): int
    {
        $configSummary = $this->entityConfigService->getConfigurationSummary();
        $issues = $this->entityConfigService->validateConfiguration();

        if ($jsonOutput) {
            $fullConfig = [
                'entity_configuration' => $configSummary,
                'validation_issues' => $issues,
                'environment_variables' => [
                    'PIPEDRIVE_ENABLED_ENTITIES' => env('PIPEDRIVE_ENABLED_ENTITIES'),
                    'PIPEDRIVE_AUTO_SYNC' => env('PIPEDRIVE_AUTO_SYNC'),
                    'PIPEDRIVE_SCHEDULER_ENABLED' => env('PIPEDRIVE_SCHEDULER_ENABLED'),
                ],
            ];

            $this->line(json_encode($fullConfig, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('🔧 Pipedrive Configuration Overview');
        $this->line('');

        // Entity configuration
        $this->showEntityConfiguration(false);

        // Additional configuration
        $this->line('🔄 Sync Configuration:');
        $this->line('  → Auto sync: '.(env('PIPEDRIVE_AUTO_SYNC', false) ? 'enabled' : 'disabled'));
        $this->line('  → Scheduler: '.(env('PIPEDRIVE_SCHEDULER_ENABLED', false) ? 'enabled' : 'disabled'));
        $this->line('');

        $this->line('📚 Available Commands:');
        $this->line('  → pipedrive:sync-entities          Sync entities manually');
        $this->line('  → pipedrive:scheduled-sync         Run scheduled sync');
        $this->line('  → pipedrive:sync-custom-fields     Sync custom fields');
        $this->line('  → pipedrive:test-connection        Test API connection');

        return self::SUCCESS;
    }
}
