<?php

namespace Skeylup\LaravelPipedrive\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Skeylup\LaravelPipedrive\Services\PipedriveAuthService;
use Carbon\Carbon;

/**
 * API health monitoring service
 * 
 * Provides continuous health checks, service degradation detection,
 * and circuit breaker integration for Pipedrive API
 */
class PipedriveHealthChecker
{
    protected array $config;
    protected PipedriveAuthService $authService;
    protected string $cachePrefix = 'pipedrive_health';

    public function __construct(PipedriveAuthService $authService, array $config = [])
    {
        $this->authService = $authService;
        $this->config = array_merge([
            'enabled' => true,
            'check_interval' => 300, // 5 minutes
            'health_endpoint' => 'currencies', // Lightweight endpoint
            'timeout' => 10,
            'failure_threshold' => 3,
            'degradation_threshold' => 1000, // 1 second response time
            'cache_ttl' => 60, // 1 minute
        ], $config);
    }

    /**
     * Check if Pipedrive API is healthy
     */
    public function isHealthy(): bool
    {
        if (!$this->config['enabled']) {
            return true;
        }

        $cacheKey = $this->getCacheKey('status');
        $cachedStatus = Cache::get($cacheKey);

        if ($cachedStatus !== null) {
            return $cachedStatus['healthy'] ?? false;
        }

        return $this->performHealthCheck()['healthy'];
    }

    /**
     * Perform health check
     */
    public function performHealthCheck(): array
    {
        $startTime = microtime(true);
        $result = [
            'healthy' => false,
            'response_time' => 0,
            'status_code' => 0,
            'error' => null,
            'checked_at' => Carbon::now()->toISOString(),
            'endpoint' => $this->config['health_endpoint'],
        ];

        try {
            $pipedrive = $this->authService->getPipedriveInstance();
            
            // Use a lightweight endpoint for health check
            $response = $this->makeHealthCheckRequest($pipedrive);
            
            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            
            $result['response_time'] = round($responseTime, 2);
            $result['status_code'] = $response->getStatusCode() ?? 200;
            $result['healthy'] = $response->isSuccess();
            
            if (!$result['healthy']) {
                $result['error'] = 'API request failed';
            }

        } catch (\Exception $e) {
            $endTime = microtime(true);
            $result['response_time'] = round(($endTime - $startTime) * 1000, 2);
            $result['error'] = $e->getMessage();
            $result['healthy'] = false;
        }

        // Cache the result
        $cacheKey = $this->getCacheKey('status');
        Cache::put($cacheKey, $result, now()->addSeconds($this->config['cache_ttl']));

        // Record health check result
        $this->recordHealthCheck($result);

        Log::debug('Pipedrive health check completed', $result);

        return $result;
    }

    /**
     * Make health check request to Pipedrive
     */
    protected function makeHealthCheckRequest($pipedrive)
    {
        $endpoint = $this->config['health_endpoint'];
        
        return match ($endpoint) {
            'currencies' => $pipedrive->currencies->all(['limit' => 1]),
            'users' => $pipedrive->users->all(['limit' => 1]),
            'pipelines' => $pipedrive->pipelines->all(['limit' => 1]),
            default => $pipedrive->currencies->all(['limit' => 1])
        };
    }

    /**
     * Check if API is experiencing degradation
     */
    public function isDegraded(): bool
    {
        $recentChecks = $this->getRecentHealthChecks(5);
        
        if (empty($recentChecks)) {
            return false;
        }

        $avgResponseTime = array_sum(array_column($recentChecks, 'response_time')) / count($recentChecks);
        
        return $avgResponseTime > $this->config['degradation_threshold'];
    }

    /**
     * Get health status with details
     */
    public function getHealthStatus(): array
    {
        $currentStatus = $this->isHealthy() ? $this->getLastHealthCheck() : $this->performHealthCheck();
        $recentChecks = $this->getRecentHealthChecks(10);
        
        $stats = $this->calculateHealthStats($recentChecks);
        
        return [
            'current_status' => $currentStatus,
            'is_healthy' => $currentStatus['healthy'],
            'is_degraded' => $this->isDegraded(),
            'stats' => $stats,
            'config' => [
                'enabled' => $this->config['enabled'],
                'check_interval' => $this->config['check_interval'],
                'health_endpoint' => $this->config['health_endpoint'],
                'failure_threshold' => $this->config['failure_threshold'],
                'degradation_threshold' => $this->config['degradation_threshold'],
            ],
        ];
    }

    /**
     * Calculate health statistics from recent checks
     */
    protected function calculateHealthStats(array $recentChecks): array
    {
        if (empty($recentChecks)) {
            return [
                'total_checks' => 0,
                'successful_checks' => 0,
                'failed_checks' => 0,
                'success_rate' => 0,
                'avg_response_time' => 0,
                'min_response_time' => 0,
                'max_response_time' => 0,
            ];
        }

        $totalChecks = count($recentChecks);
        $successfulChecks = count(array_filter($recentChecks, fn($check) => $check['healthy']));
        $failedChecks = $totalChecks - $successfulChecks;
        $responseTimes = array_column($recentChecks, 'response_time');

        return [
            'total_checks' => $totalChecks,
            'successful_checks' => $successfulChecks,
            'failed_checks' => $failedChecks,
            'success_rate' => round(($successfulChecks / $totalChecks) * 100, 2),
            'avg_response_time' => round(array_sum($responseTimes) / count($responseTimes), 2),
            'min_response_time' => min($responseTimes),
            'max_response_time' => max($responseTimes),
        ];
    }

    /**
     * Record health check result
     */
    protected function recordHealthCheck(array $result): void
    {
        $cacheKey = $this->getCacheKey('history');
        $history = Cache::get($cacheKey, []);
        
        $history[] = $result;
        
        // Keep only last 50 checks
        if (count($history) > 50) {
            array_shift($history);
        }
        
        Cache::put($cacheKey, $history, now()->addHours(24));
    }

    /**
     * Get recent health checks
     */
    public function getRecentHealthChecks(int $limit = 10): array
    {
        $cacheKey = $this->getCacheKey('history');
        $history = Cache::get($cacheKey, []);
        
        return array_slice($history, -$limit);
    }

    /**
     * Get last health check result
     */
    public function getLastHealthCheck(): ?array
    {
        $recent = $this->getRecentHealthChecks(1);
        return $recent[0] ?? null;
    }

    /**
     * Check if health checks should be performed
     */
    public function shouldPerformHealthCheck(): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        $lastCheck = $this->getLastHealthCheck();
        
        if (!$lastCheck) {
            return true;
        }

        $lastCheckTime = Carbon::parse($lastCheck['checked_at']);
        $intervalSeconds = $this->config['check_interval'];
        
        return $lastCheckTime->addSeconds($intervalSeconds)->isPast();
    }

    /**
     * Get consecutive failure count
     */
    public function getConsecutiveFailures(): int
    {
        $recentChecks = $this->getRecentHealthChecks(10);
        $consecutiveFailures = 0;
        
        // Count failures from the end
        for ($i = count($recentChecks) - 1; $i >= 0; $i--) {
            if (!$recentChecks[$i]['healthy']) {
                $consecutiveFailures++;
            } else {
                break;
            }
        }
        
        return $consecutiveFailures;
    }

    /**
     * Check if failure threshold is exceeded
     */
    public function isFailureThresholdExceeded(): bool
    {
        return $this->getConsecutiveFailures() >= $this->config['failure_threshold'];
    }

    /**
     * Reset health check history (for testing)
     */
    public function resetHealthHistory(): void
    {
        Cache::forget($this->getCacheKey('history'));
        Cache::forget($this->getCacheKey('status'));
    }

    /**
     * Get cache key for health data
     */
    protected function getCacheKey(string $type): string
    {
        return "{$this->cachePrefix}:{$type}";
    }

    /**
     * Enable health checking
     */
    public function enable(): void
    {
        $this->config['enabled'] = true;
    }

    /**
     * Disable health checking
     */
    public function disable(): void
    {
        $this->config['enabled'] = false;
    }

    /**
     * Update configuration
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get current configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
