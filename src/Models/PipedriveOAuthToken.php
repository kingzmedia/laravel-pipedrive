<?php

namespace Skeylup\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Model;

class PipedriveOAuthToken extends Model
{
    protected $table = 'pipedrive_oauth_tokens';

    protected $fillable = [
        'identifier',
        'access_token',
        'refresh_token',
        'expires_at',
        'scopes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'scopes' => 'array',
    ];

    /**
     * Check if the token is expired
     */
    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false; // Non-expiring token
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if the token needs refresh (expires in less than 5 minutes)
     */
    public function needsRefresh(): bool
    {
        if (! $this->expires_at) {
            return false; // Non-expiring token
        }

        return $this->expires_at->subMinutes(5)->isPast();
    }

    /**
     * Get the default token
     */
    public static function getDefault(): ?self
    {
        return static::where('identifier', 'default')->first();
    }

    /**
     * Create or update the default token
     */
    public static function updateDefault(array $tokenData): self
    {
        return static::updateOrCreate(
            ['identifier' => 'default'],
            $tokenData
        );
    }

    /**
     * Clear the default token
     */
    public static function clearDefault(): void
    {
        static::where('identifier', 'default')->delete();
    }
}
