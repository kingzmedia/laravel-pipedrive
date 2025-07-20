<?php

namespace Skeylup\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Skeylup\LaravelPipedrive\Services\PipedriveAuthService;
use Skeylup\LaravelPipedrive\Services\PipedriveEntityConfigService;

class ManagePipedriveWebhooksCommand extends Command
{
    public $signature = 'pipedrive:webhooks
                        {action : Action to perform (list, create, delete, test)}
                        {--url= : Webhook URL for create action}
                        {--event= : Event pattern (e.g., *.*, added.deal, updated.person)}
                        {--id= : Webhook ID for delete action}
                        {--auth-user= : HTTP Basic Auth username}
                        {--auth-pass= : HTTP Basic Auth password}
                        {--test-url : Test webhook URL connectivity before creating}
                        {--auto-config : Use configuration values as defaults}';

    public $description = 'Manage Pipedrive webhooks (list, create, delete, test) with smart configuration defaults';

    protected PipedriveAuthService $authService;

    protected PipedriveEntityConfigService $entityConfigService;

    public function __construct(
        PipedriveAuthService $authService,
        PipedriveEntityConfigService $entityConfigService
    ) {
        parent::__construct();
        $this->authService = $authService;
        $this->entityConfigService = $entityConfigService;
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        // Test connection first
        $connectionTest = $this->authService->testConnection();
        if (! $connectionTest['success']) {
            $this->error('Failed to connect to Pipedrive: '.$connectionTest['error']);

            return self::FAILURE;
        }

        if ($this->getOutput()->isVerbose()) {
            $this->info('Connected to Pipedrive as: '.$connectionTest['user'].' ('.$connectionTest['company'].')');
        }

        try {
            switch ($action) {
                case 'list':
                    return $this->listWebhooks();
                case 'create':
                    return $this->createWebhook();
                case 'delete':
                    return $this->deleteWebhook();
                case 'test':
                    return $this->testWebhookUrl();
                default:
                    $this->error('Invalid action. Use: list, create, delete, or test');

                    return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
            if ($this->getOutput()->isVerbose()) {
                $this->error('Stack trace: '.$e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    protected function listWebhooks(): int
    {
        $this->info('Fetching webhooks...');

        $client = $this->authService->getPipedriveInstance();
        $response = $client->webhooks->all();

        if (! $response->isSuccess()) {
            $this->error('Failed to fetch webhooks: '.$response->getStatusCode());

            return self::FAILURE;
        }

        $data = $response->getData();
        if (empty($data)) {
            $this->info('No webhooks found.');

            return self::SUCCESS;
        }

        $this->info('Found '.count($data).' webhook(s):');
        $this->newLine();

        foreach ($data as $webhook) {
            $this->line("ID: {$webhook->id}");
            $this->line("URL: {$webhook->subscription_url}");
            $this->line("Event: {$webhook->event_action}.{$webhook->event_object}");
            $this->line("User ID: {$webhook->user_id}");
            $this->line("Version: {$webhook->version}");
            $this->line('Active: '.($webhook->is_active ? 'Yes' : 'No'));

            if ($this->getOutput()->isVerbose()) {
                $this->line("Created: {$webhook->add_time}");
                if (! empty($webhook->http_auth_user)) {
                    $this->line("HTTP Auth User: {$webhook->http_auth_user}");
                }
                if (! empty($webhook->last_delivery_time)) {
                    $this->line("Last Delivery: {$webhook->last_delivery_time}");
                }
                if (! empty($webhook->last_http_status)) {
                    $this->line("Last HTTP Status: {$webhook->last_http_status}");
                }
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }

    protected function createWebhook(): int
    {
        $this->info('🚀 Creating Pipedrive webhook with smart configuration...');
        $this->newLine();

        // Get configuration summary
        $configSummary = $this->getWebhookConfigSummary();
        $useAutoConfig = $this->option('auto-config');

        // Display current configuration
        if ($useAutoConfig || $this->option('verbose')) {
            $this->displayWebhookConfiguration($configSummary);
        }

        // Get webhook URL
        $url = $this->getWebhookUrl($configSummary, $useAutoConfig);
        if (! $url) {
            return self::FAILURE;
        }

        // Get event pattern
        $event = $this->getWebhookEvent($configSummary, $useAutoConfig);

        // Get authentication details
        $authDetails = $this->getWebhookAuth($configSummary, $useAutoConfig);

        // Test URL if requested
        if ($this->option('test-url') || ($useAutoConfig && $this->confirm('Test webhook URL before creating?', true))) {
            $this->newLine();
            $testResult = $this->testSpecificUrl($url);
            if ($testResult !== self::SUCCESS) {
                if (! $this->confirm('Webhook URL test failed. Continue anyway?', false)) {
                    return self::FAILURE;
                }
            }
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
        if ($authDetails['username']) {
            $data['http_auth_user'] = $authDetails['username'];
            $data['http_auth_password'] = $authDetails['password'];
        }

        // Display final configuration
        $this->newLine();
        $this->info('📋 Final webhook configuration:');
        $this->line("  → URL: {$url}");
        $this->line("  → Event: {$event}");
        $this->line('  → Version: 2.0');
        if ($authDetails['username']) {
            $this->line("  → Auth: HTTP Basic ({$authDetails['username']})");
        } else {
            $this->line('  → Auth: None');
        }

        if (! $this->confirm('Create webhook with this configuration?', true)) {
            $this->info('Webhook creation cancelled.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('🔄 Creating webhook...');

        $client = $this->authService->getPipedriveInstance();

        try {
            $response = $client->webhooks->add($data);

            if ($response->isSuccess()) {
                $webhook = $response->getData();
                $this->info('✓ Webhook created successfully!');
                $this->line("ID: {$webhook->id}");
                $this->line("URL: {$webhook->subscription_url}");
                $this->line("Event: {$webhook->event_action}.{$webhook->event_object}");
            } else {
                $this->error('Failed to create webhook: '.$response->getStatusCode());
                if ($this->getOutput()->isVerbose()) {
                    $this->error('Response: '.$response->getContent());
                }

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Error creating webhook: '.$e->getMessage());
            if ($this->getOutput()->isVerbose()) {
                $this->error('Data sent: '.json_encode($data, JSON_PRETTY_PRINT));
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function deleteWebhook(): int
    {
        $webhookId = $this->option('id');

        if (! $webhookId) {
            // List webhooks first to help user choose
            $this->listWebhooks();
            $webhookId = $this->ask('Enter webhook ID to delete');
        }

        if (! is_numeric($webhookId)) {
            $this->error('Invalid webhook ID');

            return self::FAILURE;
        }

        if (! $this->confirm("Are you sure you want to delete webhook {$webhookId}?")) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $this->info("Deleting webhook {$webhookId}...");

        $client = $this->authService->getPipedriveInstance();
        $response = $client->webhooks->delete($webhookId);

        if ($response->isSuccess()) {
            $this->info("✓ Webhook {$webhookId} deleted successfully!");
        } else {
            $this->error("Failed to delete webhook {$webhookId}: ".$response->getStatusCode());
            if ($this->getOutput()->isVerbose()) {
                $this->error('Response: '.$response->getContent());
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Display webhook configuration summary
     */
    protected function displayWebhookConfiguration(array $configSummary): void
    {
        $this->line('📋 Current webhook configuration:');
        $this->line('  → App URL: '.(config('app.url') ?: 'Not configured'));
        $this->line("  → Webhook path: {$configSummary['path']}");
        $this->line('  → Full URL: '.($configSummary['url'] ?: 'Cannot build (APP_URL missing)'));
        $this->line('  → Auto-sync: '.($configSummary['auto_sync'] ? 'enabled' : 'disabled'));
        $this->line('  → Basic auth: '.($configSummary['basic_auth_enabled'] ? 'enabled' : 'disabled'));
        if ($configSummary['basic_auth_enabled'] && $configSummary['basic_auth_username']) {
            $this->line("  → Auth username: {$configSummary['basic_auth_username']}");
        }
        $this->line('  → Enabled entities: '.implode(', ', $configSummary['enabled_entities']));
        $this->newLine();
    }

    /**
     * Get webhook URL with smart defaults
     */
    protected function getWebhookUrl(array $configSummary, bool $useAutoConfig): ?string
    {
        $url = $this->option('url');

        if (! $url) {
            $defaultUrl = $configSummary['url'];

            if ($useAutoConfig && $defaultUrl) {
                $this->info("✅ Using configured webhook URL: {$defaultUrl}");

                return $defaultUrl;
            }

            if ($defaultUrl) {
                $url = $this->ask('Enter webhook URL', $defaultUrl);
            } else {
                $this->warn('⚠️  APP_URL is not configured, cannot suggest webhook URL');
                $url = $this->ask('Enter webhook URL');
            }
        }

        if (! $url) {
            $this->error('❌ Webhook URL is required');

            return null;
        }

        // Validate URL format
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error('❌ Invalid URL format');

            return null;
        }

        return $url;
    }

    /**
     * Get webhook event with smart suggestions
     */
    protected function getWebhookEvent(array $configSummary, bool $useAutoConfig): string
    {
        $event = $this->option('event');

        if (! $event) {
            $suggestedEvents = $configSummary['suggested_events'];

            if ($useAutoConfig) {
                $defaultEvent = '*.*';
                $this->info("✅ Using default event pattern: {$defaultEvent}");

                return $defaultEvent;
            }

            $this->line('💡 Suggested events based on your enabled entities:');
            foreach (array_slice($suggestedEvents, 0, 10) as $suggestedEvent) {
                $this->line("   • {$suggestedEvent}");
            }
            $this->newLine();

            $event = $this->choice(
                'Select event pattern',
                $suggestedEvents,
                '*.*'
            );
        }

        return $event;
    }

    /**
     * Get webhook authentication details
     */
    protected function getWebhookAuth(array $configSummary, bool $useAutoConfig): array
    {
        $authUser = $this->option('auth-user');
        $authPass = $this->option('auth-pass');

        // Use configuration defaults if auto-config is enabled
        if ($useAutoConfig && $configSummary['basic_auth_enabled']) {
            $configUser = $configSummary['basic_auth_username'];
            $configPass = $configSummary['basic_auth_password'];

            if ($configUser && $configPass) {
                $this->info("✅ Using configured HTTP Basic Auth: {$configUser}");

                return [
                    'username' => $configUser,
                    'password' => $configPass,
                ];
            }
        }

        // Interactive prompts
        if (! $authUser && $configSummary['basic_auth_enabled']) {
            $defaultUser = $configSummary['basic_auth_username'];
            if ($this->confirm('Enable HTTP Basic Authentication?', $defaultUser ? true : false)) {
                $authUser = $this->ask('HTTP Basic Auth username', $defaultUser);
                $authPass = $authPass ?: $this->secret('HTTP Basic Auth password');
            }
        } elseif ($authUser && ! $authPass) {
            $authPass = $this->secret('HTTP Basic Auth password');
        }

        return [
            'username' => $authUser,
            'password' => $authPass,
        ];
    }

    /**
     * Test a specific webhook URL
     */
    protected function testSpecificUrl(string $url): int
    {
        $this->info("🔍 Testing webhook URL: {$url}");

        // Temporarily set the URL option and call the test method
        $originalUrl = $this->option('url');
        $this->input->setOption('url', $url);

        $result = $this->testWebhookUrl();

        // Restore original URL option
        $this->input->setOption('url', $originalUrl);

        return $result;
    }

    /**
     * Build webhook URL from configuration
     */
    protected function buildWebhookUrl(): ?string
    {
        $appUrl = config('app.url');
        $webhookPath = config('pipedrive.webhooks.route.path', 'pipedrive/webhook');

        if (! $appUrl) {
            return null;
        }

        // Remove trailing slash from app URL and leading slash from path
        $appUrl = rtrim($appUrl, '/');
        $webhookPath = ltrim($webhookPath, '/');

        return "{$appUrl}/{$webhookPath}";
    }

    /**
     * Get webhook configuration summary
     */
    protected function getWebhookConfigSummary(): array
    {
        $config = config('pipedrive.webhooks', []);
        $basicAuth = $config['security']['basic_auth'] ?? [];
        $enabledEntities = $this->entityConfigService->getEnabledEntities();

        return [
            'url' => $this->buildWebhookUrl(),
            'path' => $config['route']['path'] ?? 'pipedrive/webhook',
            'auto_sync' => $config['auto_sync'] ?? true,
            'basic_auth_enabled' => $basicAuth['enabled'] ?? false,
            'basic_auth_username' => $basicAuth['username'] ?? null,
            'basic_auth_password' => $basicAuth['password'] ?? null,
            'enabled_entities' => $enabledEntities,
            'suggested_events' => $this->getSuggestedEvents($enabledEntities),
        ];
    }

    /**
     * Get suggested webhook events based on enabled entities
     */
    protected function getSuggestedEvents(array $enabledEntities): array
    {
        $events = ['*.*']; // Always include catch-all

        // Add specific events for enabled entities
        foreach ($enabledEntities as $entity) {
            // Convert plural entity names to singular for webhook events
            $singular = $this->entityToWebhookObject($entity);
            if ($singular) {
                $events[] = "added.{$singular}";
                $events[] = "updated.{$singular}";
                $events[] = "deleted.{$singular}";
            }
        }

        return array_unique($events);
    }

    /**
     * Convert entity name to webhook object name
     */
    protected function entityToWebhookObject(string $entity): ?string
    {
        $mapping = [
            'activities' => 'activity',
            'deals' => 'deal',
            'files' => 'file',
            'notes' => 'note',
            'organizations' => 'organization',
            'persons' => 'person',
            'pipelines' => 'pipeline',
            'products' => 'product',
            'stages' => 'stage',
            'users' => 'user',
            'goals' => 'goal',
        ];

        return $mapping[$entity] ?? null;
    }

    /**
     * Test webhook URL connectivity
     */
    protected function testWebhookUrl(): int
    {
        $url = $this->option('url');

        if (! $url) {
            $configSummary = $this->getWebhookConfigSummary();
            $url = $configSummary['url'];

            if (! $url) {
                $this->error('❌ No webhook URL provided and APP_URL is not configured');

                return self::FAILURE;
            }

            $this->info("🔍 Testing configured webhook URL: {$url}");
        } else {
            $this->info("🔍 Testing provided webhook URL: {$url}");
        }

        // Test health endpoint
        $healthUrl = rtrim($url, '/').'/health';

        try {
            $this->line("  → Testing health endpoint: {$healthUrl}");
            $response = Http::timeout(10)->get($healthUrl);

            if ($response->successful()) {
                $data = $response->json();
                $this->info('  ✅ Health check passed');
                $this->line("     Status: {$data['status']}");
                $this->line("     Service: {$data['service']}");
            } else {
                $this->warn("  ⚠️  Health endpoint returned HTTP {$response->status()}");
                $this->line('     Response: '.$response->body());
            }
        } catch (\Exception $e) {
            $this->error('  ❌ Health check failed: '.$e->getMessage());
        }

        // Test main webhook endpoint with POST
        try {
            $this->line("  → Testing main webhook endpoint: {$url}");
            $testPayload = [
                'meta' => [
                    'version' => '2.0',
                    'action' => 'added',
                    'object' => 'deal',
                    'entity_id' => 'test-webhook-validation',
                    'user_id' => 1,
                    'company_id' => 1,
                    'timestamp' => now()->toISOString(),
                ],
                'data' => [
                    'id' => 'test-webhook-validation',
                    'title' => 'Test Webhook Validation',
                ],
            ];

            $request = Http::timeout(10)->post($url, $testPayload);

            if ($request->successful()) {
                $this->info('  ✅ Webhook endpoint is accessible');
                $response = $request->json();
                if (isset($response['status'])) {
                    $this->line("     Response status: {$response['status']}");
                }
            } else {
                $this->warn("  ⚠️  Webhook endpoint returned HTTP {$request->status()}");
                $this->line('     This might be expected if webhook validation is enabled');
            }
        } catch (\Exception $e) {
            $this->error('  ❌ Webhook endpoint test failed: '.$e->getMessage());
        }

        $this->newLine();
        $this->info('🔧 Webhook URL testing completed');

        return self::SUCCESS;
    }
}
