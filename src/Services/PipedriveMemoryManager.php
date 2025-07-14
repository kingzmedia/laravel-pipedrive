<?php

namespace Keggermont\LaravelPipedrive\Services;

use Illuminate\Support\Facades\Log;
use Keggermont\LaravelPipedrive\Exceptions\PipedriveMemoryException;

/**
 * Adaptive memory management service
 * 
 * Provides real-time memory monitoring, adaptive pagination,
 * and memory alerts for Pipedrive operations
 */
class PipedriveMemoryManager
{
    protected array $config;
    protected int $initialBatchSize;
    protected int $currentBatchSize;
    protected array $memoryHistory = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'adaptive_pagination' => true,
            'memory_threshold_percent' => 80,
            'min_batch_size' => 10,
            'max_batch_size' => 500,
            'force_gc' => true,
            'alert_threshold_percent' => 85,
            'critical_threshold_percent' => 95,
        ], $config);

        $this->initialBatchSize = $this->config['max_batch_size'];
        $this->currentBatchSize = $this->initialBatchSize;
    }

    /**
     * Check if memory usage is within safe limits
     */
    public function isMemorySafe(): bool
    {
        $usagePercent = $this->getMemoryUsagePercent();
        return $usagePercent < $this->config['memory_threshold_percent'];
    }

    /**
     * Get current memory usage percentage
     */
    public function getMemoryUsagePercent(): float
    {
        $memoryUsed = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimitInBytes();
        
        if ($memoryLimit === 0) {
            return 0.0;
        }

        return ($memoryUsed / $memoryLimit) * 100;
    }

    /**
     * Get current memory usage in bytes
     */
    public function getMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    /**
     * Get memory limit in bytes
     */
    public function getMemoryLimit(): int
    {
        return $this->getMemoryLimitInBytes();
    }

    /**
     * Get available memory in bytes
     */
    public function getAvailableMemory(): int
    {
        return max(0, $this->getMemoryLimit() - $this->getMemoryUsage());
    }

    /**
     * Get adaptive batch size based on current memory usage
     */
    public function getAdaptiveBatchSize(): int
    {
        if (!$this->config['adaptive_pagination']) {
            return $this->currentBatchSize;
        }

        $usagePercent = $this->getMemoryUsagePercent();
        $threshold = $this->config['memory_threshold_percent'];

        // If memory usage is high, reduce batch size
        if ($usagePercent > $threshold) {
            $reductionFactor = min(0.5, ($usagePercent - $threshold) / 20); // Max 50% reduction
            $newBatchSize = (int) ($this->currentBatchSize * (1 - $reductionFactor));
            $this->currentBatchSize = max($this->config['min_batch_size'], $newBatchSize);
            
            Log::info('Reduced batch size due to memory usage', [
                'memory_usage_percent' => $usagePercent,
                'old_batch_size' => $this->currentBatchSize / (1 - $reductionFactor),
                'new_batch_size' => $this->currentBatchSize,
                'reduction_factor' => $reductionFactor,
            ]);
        }
        // If memory usage is low, gradually increase batch size
        elseif ($usagePercent < ($threshold - 20) && $this->currentBatchSize < $this->initialBatchSize) {
            $increaseFactor = 0.1; // 10% increase
            $newBatchSize = (int) ($this->currentBatchSize * (1 + $increaseFactor));
            $this->currentBatchSize = min($this->config['max_batch_size'], $newBatchSize);
            
            Log::debug('Increased batch size due to low memory usage', [
                'memory_usage_percent' => $usagePercent,
                'old_batch_size' => $this->currentBatchSize / (1 + $increaseFactor),
                'new_batch_size' => $this->currentBatchSize,
            ]);
        }

        return $this->currentBatchSize;
    }

    /**
     * Monitor memory usage and trigger alerts if needed
     */
    public function monitorMemoryUsage(string $operation = 'unknown'): void
    {
        $usagePercent = $this->getMemoryUsagePercent();
        $memoryUsed = $this->getMemoryUsage();
        
        // Record memory usage history
        $this->recordMemoryUsage($usagePercent, $operation);

        // Check for alerts
        if ($usagePercent >= $this->config['critical_threshold_percent']) {
            $this->triggerCriticalMemoryAlert($usagePercent, $memoryUsed, $operation);
        } elseif ($usagePercent >= $this->config['alert_threshold_percent']) {
            $this->triggerMemoryAlert($usagePercent, $memoryUsed, $operation);
        }

        // Force garbage collection if enabled and memory usage is high
        if ($this->config['force_gc'] && $usagePercent > $this->config['memory_threshold_percent']) {
            $this->forceGarbageCollection();
        }
    }

    /**
     * Force garbage collection and log results
     */
    public function forceGarbageCollection(): void
    {
        $memoryBefore = memory_get_usage(true);
        
        gc_collect_cycles();
        
        $memoryAfter = memory_get_usage(true);
        $memoryFreed = $memoryBefore - $memoryAfter;
        
        if ($memoryFreed > 0) {
            Log::debug('Garbage collection freed memory', [
                'memory_before' => $this->formatBytes($memoryBefore),
                'memory_after' => $this->formatBytes($memoryAfter),
                'memory_freed' => $this->formatBytes($memoryFreed),
                'usage_percent_before' => ($memoryBefore / $this->getMemoryLimit()) * 100,
                'usage_percent_after' => ($memoryAfter / $this->getMemoryLimit()) * 100,
            ]);
        }
    }

    /**
     * Check if memory usage exceeds threshold and throw exception
     */
    public function checkMemoryThreshold(string $operation = 'unknown', int $batchSize = 0): void
    {
        $usagePercent = $this->getMemoryUsagePercent();
        
        if ($usagePercent > $this->config['critical_threshold_percent']) {
            throw PipedriveMemoryException::fromCurrentMemoryUsage($operation, $batchSize);
        }
    }

    /**
     * Record memory usage in history
     */
    protected function recordMemoryUsage(float $usagePercent, string $operation): void
    {
        $this->memoryHistory[] = [
            'timestamp' => time(),
            'usage_percent' => $usagePercent,
            'operation' => $operation,
        ];

        // Keep only last 100 records
        if (count($this->memoryHistory) > 100) {
            array_shift($this->memoryHistory);
        }
    }

    /**
     * Trigger memory alert
     */
    protected function triggerMemoryAlert(float $usagePercent, int $memoryUsed, string $operation): void
    {
        Log::warning('High memory usage detected', [
            'memory_usage_percent' => $usagePercent,
            'memory_used' => $this->formatBytes($memoryUsed),
            'memory_limit' => $this->formatBytes($this->getMemoryLimit()),
            'available_memory' => $this->formatBytes($this->getAvailableMemory()),
            'operation' => $operation,
            'current_batch_size' => $this->currentBatchSize,
            'threshold' => $this->config['alert_threshold_percent'],
        ]);
    }

    /**
     * Trigger critical memory alert
     */
    protected function triggerCriticalMemoryAlert(float $usagePercent, int $memoryUsed, string $operation): void
    {
        Log::error('Critical memory usage detected', [
            'memory_usage_percent' => $usagePercent,
            'memory_used' => $this->formatBytes($memoryUsed),
            'memory_limit' => $this->formatBytes($this->getMemoryLimit()),
            'available_memory' => $this->formatBytes($this->getAvailableMemory()),
            'operation' => $operation,
            'current_batch_size' => $this->currentBatchSize,
            'threshold' => $this->config['critical_threshold_percent'],
            'suggested_actions' => [
                'Reduce batch size',
                'Increase PHP memory limit',
                'Process data in smaller chunks',
                'Enable garbage collection',
            ],
        ]);
    }

    /**
     * Get memory usage statistics
     */
    public function getMemoryStats(): array
    {
        return [
            'memory_used' => $this->getMemoryUsage(),
            'memory_used_formatted' => $this->formatBytes($this->getMemoryUsage()),
            'memory_limit' => $this->getMemoryLimit(),
            'memory_limit_formatted' => $this->formatBytes($this->getMemoryLimit()),
            'available_memory' => $this->getAvailableMemory(),
            'available_memory_formatted' => $this->formatBytes($this->getAvailableMemory()),
            'usage_percent' => $this->getMemoryUsagePercent(),
            'is_memory_safe' => $this->isMemorySafe(),
            'current_batch_size' => $this->currentBatchSize,
            'initial_batch_size' => $this->initialBatchSize,
            'adaptive_pagination_enabled' => $this->config['adaptive_pagination'],
            'thresholds' => [
                'memory_threshold' => $this->config['memory_threshold_percent'],
                'alert_threshold' => $this->config['alert_threshold_percent'],
                'critical_threshold' => $this->config['critical_threshold_percent'],
            ],
        ];
    }

    /**
     * Get memory usage history
     */
    public function getMemoryHistory(): array
    {
        return $this->memoryHistory;
    }

    /**
     * Reset batch size to initial value
     */
    public function resetBatchSize(): void
    {
        $this->currentBatchSize = $this->initialBatchSize;
    }

    /**
     * Format bytes to human-readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get PHP memory limit in bytes
     */
    protected function getMemoryLimitInBytes(): int
    {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return PHP_INT_MAX;
        }

        $value = (int) $memoryLimit;
        $unit = strtolower(substr($memoryLimit, -1));

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
    }
}
