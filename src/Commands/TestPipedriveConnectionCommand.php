<?php

namespace Skeylup\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Skeylup\LaravelPipedrive\Services\PipedriveAuthService;

class TestPipedriveConnectionCommand extends Command
{
    public $signature = 'pipedrive:test-connection';

    public $description = 'Test the connection to Pipedrive API';

    protected PipedriveAuthService $authService;

    public function __construct(PipedriveAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    public function handle(): int
    {
        $this->info('Testing Pipedrive connection...');
        $this->newLine();

        // Show configuration
        $authMethod = $this->authService->getAuthMethod();
        $this->info("Authentication method: <fg=yellow>{$authMethod}</>");

        if ($authMethod === 'token') {
            $token = config('pipedrive.token');
            $tokenDisplay = $token ? substr($token, 0, 8).'...' : 'NOT SET';
            $this->info("API Token: <fg=yellow>{$tokenDisplay}</>");
        } else {
            $clientId = config('pipedrive.oauth.client_id');
            $this->info('OAuth Client ID: <fg=yellow>'.($clientId ?: 'NOT SET').'</>');

            // Show token status for OAuth
            $tokenStatus = $this->authService->getTokenStatus();
            $this->info("Token Status: <fg=yellow>{$tokenStatus['status']}</>");

            if (isset($tokenStatus['expires_at_human'])) {
                $this->info("Token Expires: <fg=yellow>{$tokenStatus['expires_at_human']}</>");
                $this->info('Needs Refresh: <fg=yellow>'.($tokenStatus['needs_refresh'] ? 'Yes' : 'No').'</>');
            }
        }

        $this->newLine();

        // Test connection
        $result = $this->authService->testConnection();

        if ($result['success']) {
            $this->info('✅ <fg=green>Connection successful!</>');
            $this->info("User: <fg=cyan>{$result['user']}</>");
            $this->info("Company: <fg=cyan>{$result['company']}</>");

            return self::SUCCESS;
        } else {
            $this->error('❌ <fg=red>Connection failed!</>');
            $this->error("Error: {$result['message']}");

            if (isset($result['error'])) {
                $this->newLine();
                $this->warn('Debug information:');
                $this->line($result['error']);
            }

            $this->newLine();
            $this->warn('💡 Troubleshooting tips:');

            if ($authMethod === 'token') {
                $this->line('• Make sure PIPEDRIVE_TOKEN is set in your .env file');
                $this->line('• Verify your API token in Pipedrive: Settings > Personal preferences > API');
                $this->line('• Check if your token has the necessary permissions');
            } else {
                $this->line('• Make sure PIPEDRIVE_CLIENT_ID, PIPEDRIVE_CLIENT_SECRET, and PIPEDRIVE_REDIRECT_URL are set');
                $this->line('• Verify your OAuth app configuration in Pipedrive Developer Hub');
                $this->line('• Ensure you have completed the OAuth authorization flow');
            }

            return self::FAILURE;
        }
    }
}
