<?php

namespace Skeylup\LaravelPipedrive\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Skeylup\LaravelPipedrive\Models\PipedriveOAuthToken;

class MigratePipedriveTokenCommand extends Command
{
    public $signature = 'pipedrive:migrate-token {--force : Skip confirmation}';

    public $description = 'Migrate OAuth token from cache to persistent database storage';

    public function handle(): int
    {
        $this->info('🔄 Migrating Pipedrive OAuth token from cache to database...');
        $this->newLine();

        // Check if token already exists in database
        $existingToken = PipedriveOAuthToken::getDefault();
        if ($existingToken) {
            $this->warn('⚠️  Token already exists in database:');
            $this->info('Created: '.$existingToken->created_at->format('Y-m-d H:i:s'));
            $this->info('Expires: '.($existingToken->expires_at ? $existingToken->expires_at->format('Y-m-d H:i:s') : 'Never'));
            $this->info('Status: '.($existingToken->isExpired() ? 'Expired' : 'Valid'));

            if (! $this->option('force')) {
                if (! $this->confirm('Do you want to overwrite the existing token?')) {
                    $this->info('Migration cancelled.');

                    return self::SUCCESS;
                }
            }
        }

        // Try to get token from cache
        $cacheToken = $this->getTokenFromCache();

        if (! $cacheToken) {
            $this->error('❌ No token found in cache. Please re-authenticate via OAuth.');

            return self::FAILURE;
        }

        $this->info('✅ Found token in cache:');
        $this->info('Access token: '.substr($cacheToken['access_token'], 0, 20).'...');
        $this->info('Expires at: '.date('Y-m-d H:i:s', $cacheToken['expires_at']));
        $this->info('Expired: '.($cacheToken['expires_at'] < time() ? 'YES' : 'NO'));
        $this->newLine();

        if (! $this->option('force')) {
            if (! $this->confirm('Migrate this token to database?')) {
                $this->info('Migration cancelled.');

                return self::SUCCESS;
            }
        }

        try {
            // Create or update token in database
            PipedriveOAuthToken::updateDefault([
                'access_token' => $cacheToken['access_token'],
                'refresh_token' => $cacheToken['refresh_token'] ?? null,
                'expires_at' => Carbon::createFromTimestamp($cacheToken['expires_at']),
            ]);

            $this->info('✅ <fg=green>Token successfully migrated to database!</>');
            $this->newLine();

            // Ask if user wants to clear cache token
            if ($this->confirm('Clear the token from cache?', true)) {
                Cache::forget('pipedrive_oauth_token');
                $this->info('✅ Cache token cleared.');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to migrate token: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Get token from cache (handles both direct cache and database cache driver)
     */
    protected function getTokenFromCache(): ?array
    {
        // Try Laravel Cache facade first
        $token = Cache::get('pipedrive_oauth_token');
        if ($token) {
            return $token;
        }

        // If using database cache driver, try direct database query
        if (config('cache.default') === 'database') {
            $cacheEntry = DB::table('cache')->where('key', 'pipedrive_oauth_token')->first();
            if ($cacheEntry && $cacheEntry->expiration > time()) {
                return unserialize($cacheEntry->value);
            }
        }

        return null;
    }
}
