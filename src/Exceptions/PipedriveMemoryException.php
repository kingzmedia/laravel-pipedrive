<?php

namespace Keggermont\LaravelPipedrive\Exceptions;

use Throwable;

/**
 * Exception for memory-related errors
 * 
 * Handles memory limit issues during data processing
 */
class PipedriveMemoryException extends PipedriveException
{
    protected int $memoryUsed = 0;
    protected int $memoryLimit = 0;
    protected float $memoryUsagePercent = 0.0;
    protected int $batchSize = 0;
    protected int $recommendedBatchSize = 0;
    protected string $operation = 'unknown';

    public function __construct(
        string $message = 'Memory limit exceeded during Pipedrive operation',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
        bool $retryable = true,
        int $retryAfter = 5,
        int $maxRetries = 3,
        int $memoryUsed = 0,
        int $memoryLimit = 0,
        float $memoryUsagePercent = 0.0,
        int $batchSize = 0,
        int $recommendedBatchSize = 0,
        string $operation = 'unknown'
    ) {
        parent::__construct(
            message: $message,
            code: $code,
            previous: $previous,
            context: $context,
            retryable: $retryable,
            retryAfter: $retryAfter,
            maxRetries: $maxRetries,
            errorType: 'memory'
        );

        $this->memoryUsed = $memoryUsed;
        $this->memoryLimit = $memoryLimit;
        $this->memoryUsagePercent = $memoryUsagePercent;
        $this->batchSize = $batchSize;
        $this->recommendedBatchSize = $recommendedBatchSize;
        $this->operation = $operation;
    }

    /**
     * Get memory used in bytes
     */
    public function getMemoryUsed(): int
    {
        return $this->memoryUsed;
    }

    /**
     * Set memory used in bytes
     */
    public function setMemoryUsed(int $memoryUsed): self
    {
        $this->memoryUsed = $memoryUsed;
        return $this;
    }

    /**
     * Get memory limit in bytes
     */
    public function getMemoryLimit(): int
    {
        return $this->memoryLimit;
    }

    /**
     * Set memory limit in bytes
     */
    public function setMemoryLimit(int $memoryLimit): self
    {
        $this->memoryLimit = $memoryLimit;
        return $this;
    }

    /**
     * Get memory usage percentage
     */
    public function getMemoryUsagePercent(): float
    {
        return $this->memoryUsagePercent;
    }

    /**
     * Set memory usage percentage
     */
    public function setMemoryUsagePercent(float $memoryUsagePercent): self
    {
        $this->memoryUsagePercent = $memoryUsagePercent;
        return $this;
    }

    /**
     * Get current batch size
     */
    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * Set current batch size
     */
    public function setBatchSize(int $batchSize): self
    {
        $this->batchSize = $batchSize;
        return $this;
    }

    /**
     * Get recommended batch size
     */
    public function getRecommendedBatchSize(): int
    {
        return $this->recommendedBatchSize;
    }

    /**
     * Set recommended batch size
     */
    public function setRecommendedBatchSize(int $recommendedBatchSize): self
    {
        $this->recommendedBatchSize = $recommendedBatchSize;
        return $this;
    }

    /**
     * Get operation that caused the memory issue
     */
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * Set operation that caused the memory issue
     */
    public function setOperation(string $operation): self
    {
        $this->operation = $operation;
        return $this;
    }

    /**
     * Get memory used in human-readable format
     */
    public function getMemoryUsedFormatted(): string
    {
        return $this->formatBytes($this->memoryUsed);
    }

    /**
     * Get memory limit in human-readable format
     */
    public function getMemoryLimitFormatted(): string
    {
        return $this->formatBytes($this->memoryLimit);
    }

    /**
     * Get available memory in bytes
     */
    public function getAvailableMemory(): int
    {
        return max(0, $this->memoryLimit - $this->memoryUsed);
    }

    /**
     * Get available memory in human-readable format
     */
    public function getAvailableMemoryFormatted(): string
    {
        return $this->formatBytes($this->getAvailableMemory());
    }

    /**
     * Check if memory usage is critical (>90%)
     */
    public function isCriticalMemoryUsage(): bool
    {
        return $this->memoryUsagePercent > 90.0;
    }

    /**
     * Get suggested actions to resolve memory issue
     */
    public function getSuggestedActions(): array
    {
        $actions = [];

        if ($this->recommendedBatchSize > 0 && $this->recommendedBatchSize < $this->batchSize) {
            $actions[] = "Reduce batch size from {$this->batchSize} to {$this->recommendedBatchSize}";
        }

        if ($this->memoryUsagePercent > 80) {
            $actions[] = 'Increase PHP memory limit';
            $actions[] = 'Process data in smaller chunks';
        }

        $actions[] = 'Enable garbage collection between batches';
        $actions[] = 'Consider using streaming/cursor-based processing';

        return $actions;
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
     * Get formatted error information for logging
     */
    public function getErrorInfo(): array
    {
        $info = parent::getErrorInfo();
        
        $info['memory'] = [
            'memory_used' => $this->memoryUsed,
            'memory_used_formatted' => $this->getMemoryUsedFormatted(),
            'memory_limit' => $this->memoryLimit,
            'memory_limit_formatted' => $this->getMemoryLimitFormatted(),
            'memory_usage_percent' => $this->memoryUsagePercent,
            'available_memory' => $this->getAvailableMemory(),
            'available_memory_formatted' => $this->getAvailableMemoryFormatted(),
            'batch_size' => $this->batchSize,
            'recommended_batch_size' => $this->recommendedBatchSize,
            'operation' => $this->operation,
            'is_critical' => $this->isCriticalMemoryUsage(),
            'suggested_actions' => $this->getSuggestedActions(),
        ];

        return $info;
    }

    /**
     * Convert exception to array for serialization
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['memory'] = [
            'memory_used' => $this->memoryUsed,
            'memory_limit' => $this->memoryLimit,
            'memory_usage_percent' => $this->memoryUsagePercent,
            'batch_size' => $this->batchSize,
            'recommended_batch_size' => $this->recommendedBatchSize,
            'operation' => $this->operation,
        ];

        return $array;
    }

    /**
     * Create exception from current memory usage
     */
    public static function fromCurrentMemoryUsage(
        string $operation = 'sync',
        int $batchSize = 0,
        ?string $customMessage = null
    ): static {
        $memoryUsed = memory_get_usage(true);
        $memoryLimit = self::getMemoryLimitInBytes();
        $memoryUsagePercent = $memoryLimit > 0 ? ($memoryUsed / $memoryLimit) * 100 : 0;

        $message = $customMessage ?? "Memory usage at {$memoryUsagePercent}% during {$operation}";
        
        // Calculate recommended batch size (reduce by 50% if over 80% memory usage)
        $recommendedBatchSize = $memoryUsagePercent > 80 && $batchSize > 0 
            ? max(10, (int) ($batchSize * 0.5)) 
            : $batchSize;

        return new static(
            message: $message,
            memoryUsed: $memoryUsed,
            memoryLimit: $memoryLimit,
            memoryUsagePercent: $memoryUsagePercent,
            batchSize: $batchSize,
            recommendedBatchSize: $recommendedBatchSize,
            operation: $operation
        );
    }

    /**
     * Get PHP memory limit in bytes
     */
    protected static function getMemoryLimitInBytes(): int
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
