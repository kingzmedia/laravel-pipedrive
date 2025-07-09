<?php

namespace Keggermont\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Keggermont\LaravelPipedrive\Services\PipedriveAuthService;

class ManagePipedriveWebhooksCommand extends Command
{
    public $signature = 'pipedrive:webhooks 
                        {action : Action to perform (list, create, delete)}
                        {--url= : Webhook URL for create action}
                        {--event= : Event pattern (e.g., *.*, added.deal, updated.person)}
                        {--id= : Webhook ID for delete action}
                        {--auth-user= : HTTP Basic Auth username}
                        {--auth-pass= : HTTP Basic Auth password}
                        {--v|verbose : Show detailed output}';

    public $description = 'Manage Pipedrive webhooks (list, create, delete)';

    protected PipedriveAuthService $authService;

    public function __construct(PipedriveAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        // Test connection first
        $connectionTest = $this->authService->testConnection();
        if (!$connectionTest['success']) {
            $this->error('Failed to connect to Pipedrive: ' . $connectionTest['error']);
            return self::FAILURE;
        }

        if ($this->option('verbose')) {
            $this->info('Connected to Pipedrive as: ' . $connectionTest['user'] . ' (' . $connectionTest['company'] . ')');
        }

        try {
            switch ($action) {
                case 'list':
                    return $this->listWebhooks();
                case 'create':
                    return $this->createWebhook();
                case 'delete':
                    return $this->deleteWebhook();
                default:
                    $this->error('Invalid action. Use: list, create, or delete');
                    return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            if ($this->option('verbose')) {
                $this->error('Stack trace: ' . $e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    protected function listWebhooks(): int
    {
        $this->info('Fetching webhooks...');

        $client = $this->authService->getClient();
        $response = $client->webhooks->all();

        if (empty($response['data'])) {
            $this->info('No webhooks found.');
            return self::SUCCESS;
        }

        $this->info('Found ' . count($response['data']) . ' webhook(s):');
        $this->newLine();

        foreach ($response['data'] as $webhook) {
            $this->line("ID: {$webhook['id']}");
            $this->line("URL: {$webhook['subscription_url']}");
            $this->line("Event: {$webhook['event_action']}.{$webhook['event_object']}");
            $this->line("User ID: {$webhook['user_id']}");
            $this->line("Version: {$webhook['version']}");
            $this->line("Active: " . ($webhook['active_flag'] ? 'Yes' : 'No'));
            
            if ($this->option('verbose')) {
                $this->line("Created: {$webhook['add_time']}");
                $this->line("Updated: {$webhook['update_time']}");
                if (!empty($webhook['http_auth_user'])) {
                    $this->line("HTTP Auth User: {$webhook['http_auth_user']}");
                }
            }
            
            $this->newLine();
        }

        return self::SUCCESS;
    }

    protected function createWebhook(): int
    {
        $url = $this->option('url');
        $event = $this->option('event');

        if (!$url) {
            $url = $this->ask('Enter webhook URL');
        }

        if (!$event) {
            $event = $this->choice(
                'Select event pattern',
                ['*.*', 'added.*', 'updated.*', 'deleted.*', 'added.deal', 'updated.deal', 'added.person', 'updated.person'],
                '*.*'
            );
        }

        // Parse event pattern
        [$eventAction, $eventObject] = explode('.', $event, 2);

        $data = [
            'subscription_url' => $url,
            'event_action' => $eventAction,
            'event_object' => $eventObject,
            'version' => '2.0', // Use webhooks v2
        ];

        // Add HTTP Basic Auth if provided
        if ($this->option('auth-user')) {
            $data['http_auth_user'] = $this->option('auth-user');
            $data['http_auth_password'] = $this->option('auth-pass') ?: 
                $this->secret('Enter HTTP Basic Auth password');
        }

        $this->info("Creating webhook...");
        if ($this->option('verbose')) {
            $this->line("URL: {$url}");
            $this->line("Event: {$event}");
        }

        $client = $this->authService->getClient();
        $response = $client->webhooks->add($data);

        if ($response['success']) {
            $webhook = $response['data'];
            $this->info("✓ Webhook created successfully!");
            $this->line("ID: {$webhook['id']}");
            $this->line("URL: {$webhook['subscription_url']}");
            $this->line("Event: {$webhook['event_action']}.{$webhook['event_object']}");
        } else {
            $this->error("Failed to create webhook");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function deleteWebhook(): int
    {
        $webhookId = $this->option('id');

        if (!$webhookId) {
            // List webhooks first to help user choose
            $this->listWebhooks();
            $webhookId = $this->ask('Enter webhook ID to delete');
        }

        if (!is_numeric($webhookId)) {
            $this->error('Invalid webhook ID');
            return self::FAILURE;
        }

        if (!$this->confirm("Are you sure you want to delete webhook {$webhookId}?")) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $this->info("Deleting webhook {$webhookId}...");

        $client = $this->authService->getClient();
        $response = $client->webhooks->delete($webhookId);

        if ($response['success']) {
            $this->info("✓ Webhook {$webhookId} deleted successfully!");
        } else {
            $this->error("Failed to delete webhook {$webhookId}");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
