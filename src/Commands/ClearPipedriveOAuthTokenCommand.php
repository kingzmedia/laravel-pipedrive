<?php

namespace Skeylup\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Skeylup\LaravelPipedrive\Services\PipedriveAuthService;

class ClearPipedriveOAuthTokenCommand extends Command
{
    public $signature = 'pipedrive:clear-oauth-token {--force : Skip confirmation}';

    public $description = 'Clear the stored OAuth token for Pipedrive';

    protected PipedriveAuthService $authService;

    public function __construct(PipedriveAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    public function handle(): int
    {
        if (! $this->authService->isUsingOAuth()) {
            $this->error('❌ OAuth is not configured. Current auth method: '.$this->authService->getAuthMethod());

            return self::FAILURE;
        }

        // Show current token status
        $tokenStatus = $this->authService->getTokenStatus();
        $this->info('Current token status: '.$tokenStatus['status']);

        if (isset($tokenStatus['expires_at_human'])) {
            $this->info('Token expires: '.$tokenStatus['expires_at_human']);
        }

        $this->newLine();

        // Confirm action
        if (! $this->option('force')) {
            if (! $this->confirm('Are you sure you want to clear the OAuth token? You will need to re-authenticate.')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        try {
            $this->authService->clearToken();
            $this->info('✅ <fg=green>OAuth token cleared successfully!</>');
            $this->newLine();
            $this->warn('💡 You will need to re-authenticate by visiting: /pipedrive/oauth/authorize');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to clear token: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
