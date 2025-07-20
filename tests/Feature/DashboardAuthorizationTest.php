<?php

namespace Skeylup\LaravelPipedrive\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Skeylup\LaravelPipedrive\Tests\Models\User;
use Skeylup\LaravelPipedrive\Tests\TestCase;

class DashboardAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->authorizedUser = User::create([
            'name' => 'Authorized User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->unauthorizedUser = User::create([
            'name' => 'Unauthorized User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    /** @test */
    public function it_allows_access_in_local_environment()
    {
        // Set local environment
        app()->detectEnvironment(function () {
            return 'local';
        });

        // Test OAuth routes
        $response = $this->actingAs($this->unauthorizedUser)
            ->get('/pipedrive/oauth/status');

        $response->assertStatus(200);

        // Test webhook health route
        $response = $this->actingAs($this->unauthorizedUser)
            ->get('/pipedrive/webhook/health');

        $response->assertStatus(200);
    }

    /** @test */
    public function webhook_health_allows_authorized_users()
    {
        // Set production environment
        app()->detectEnvironment(function () {
            return 'production';
        });

        // Configure authorized emails
        config(['pipedrive.dashboard.authorized_emails' => ['admin@example.com']]);

        // Test webhook health with authorized user
        $response = $this->actingAs($this->authorizedUser)
            ->get('/pipedrive/webhook/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'service' => 'Laravel Pipedrive Webhooks',
        ]);
    }

    /** @test */
    public function webhook_health_allows_webhook_basic_auth()
    {
        // Set production environment
        app()->detectEnvironment(function () {
            return 'production';
        });

        // Configure webhook basic auth
        config([
            'pipedrive.webhooks.security.basic_auth.enabled' => true,
            'pipedrive.webhooks.security.basic_auth.username' => 'webhook_user',
            'pipedrive.webhooks.security.basic_auth.password' => 'webhook_pass',
        ]);

        // Test webhook health with basic auth (simulating Pipedrive server)
        $response = $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode('webhook_user:webhook_pass'),
        ])->get('/pipedrive/webhook/health');

        $response->assertStatus(200);
    }

    /** @test */
    public function webhook_health_denies_unauthorized_access()
    {
        // Set production environment
        app()->detectEnvironment(function () {
            return 'production';
        });

        // Configure webhook basic auth (so it's not open access)
        config([
            'pipedrive.webhooks.security.basic_auth.enabled' => true,
            'pipedrive.webhooks.security.basic_auth.username' => 'webhook_user',
            'pipedrive.webhooks.security.basic_auth.password' => 'webhook_pass',
        ]);

        // Test webhook health without authorization
        $response = $this->actingAs($this->unauthorizedUser)
            ->get('/pipedrive/webhook/health');

        $response->assertStatus(403);
    }
}
