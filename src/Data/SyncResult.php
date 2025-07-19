<?php

namespace Skeylup\LaravelPipedrive\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

/**
 * Data Transfer Object for sync operation results
 * 
 * Standardizes sync results across jobs and commands
 */
class SyncResult extends Data
{
    public function __construct(
        public bool $success,
        public string $entityType,
        public int $synced = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $errors = 0,
        public int $totalProcessed = 0,
        public float $executionTime = 0.0,
        public array $processedItems = [],
        public array $errorItems = [],
        public array $memoryStats = [],
        public array $rateLimitStats = [],
        public array $healthStats = [],
        public ?string $errorMessage = null,
        public ?array $exception = null,
        public array $metadata = [],
        public string $context = 'sync',
        public ?string $startedAt = null,
        public ?string $completedAt = null,
        public array $progressData = [],
    ) {
        $this->totalProcessed = $this->synced + $this->updated + $this->skipped + $this->errors;
        $this->startedAt = $this->startedAt ?? Carbon::now()->toISOString();
        $this->completedAt = $this->completedAt ?? Carbon::now()->toISOString();
    }

    /**
     * Create successful result
     */
    public static function success(
        string $entityType,
        int $synced = 0,
        int $updated = 0,
        int $skipped = 0,
        int $errors = 0,
        array $metadata = []
    ): self {
        return new self(
            success: true,
            entityType: $entityType,
            synced: $synced,
            updated: $updated,
            skipped: $skipped,
            errors: $errors,
            metadata: $metadata
        );
    }

    /**
     * Create failed result
     */
    public static function failure(
        string $entityType,
        string $errorMessage,
        ?\Throwable $exception = null,
        array $metadata = []
    ): self {
        $exceptionData = null;
        if ($exception) {
            $exceptionData = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];

            // Add Pipedrive-specific exception data
            if (method_exists($exception, 'getErrorInfo')) {
                $exceptionData['pipedrive_info'] = $exception->getErrorInfo();
            }
        }

        return new self(
            success: false,
            entityType: $entityType,
            errorMessage: $errorMessage,
            exception: $exceptionData,
            metadata: $metadata
        );
    }

    /**
     * Create result from processing data
     */
    public static function fromProcessingData(
        string $entityType,
        array $processingResult,
        float $executionTime = 0.0,
        array $metadata = []
    ): self {
        return new self(
            success: ($processingResult['errors'] ?? 0) === 0,
            entityType: $entityType,
            synced: $processingResult['synced'] ?? 0,
            updated: $processingResult['updated'] ?? 0,
            skipped: $processingResult['skipped'] ?? 0,
            errors: $processingResult['errors'] ?? 0,
            executionTime: $executionTime,
            processedItems: $processingResult['processed_items'] ?? [],
            errorItems: $processingResult['error_items'] ?? [],
            metadata: $metadata
        );
    }

    /**
     * Add memory statistics
     */
    public function withMemoryStats(array $memoryStats): self
    {
        $this->memoryStats = $memoryStats;
        return $this;
    }

    /**
     * Add rate limit statistics
     */
    public function withRateLimitStats(array $rateLimitStats): self
    {
        $this->rateLimitStats = $rateLimitStats;
        return $this;
    }

    /**
     * Add health statistics
     */
    public function withHealthStats(array $healthStats): self
    {
        $this->healthStats = $healthStats;
        return $this;
    }

    /**
     * Add progress data
     */
    public function withProgressData(array $progressData): self
    {
        $this->progressData = $progressData;
        return $this;
    }

    /**
     * Set execution timing
     */
    public function withTiming(string $startedAt, ?string $completedAt = null): self
    {
        $this->startedAt = $startedAt;
        $this->completedAt = $completedAt ?? Carbon::now()->toISOString();
        
        // Calculate execution time
        $start = Carbon::parse($this->startedAt);
        $end = Carbon::parse($this->completedAt);
        $this->executionTime = $end->diffInSeconds($start, true);
        
        return $this;
    }

    /**
     * Add context information
     */
    public function withContext(string $context, array $metadata = []): self
    {
        $this->context = $context;
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    /**
     * Check if sync was successful
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if sync failed
     */
    public function isFailure(): bool
    {
        return !$this->success;
    }

    /**
     * Check if there were any errors during processing
     */
    public function hasErrors(): bool
    {
        return $this->errors > 0;
    }

    /**
     * Check if any items were processed
     */
    public function hasProcessedItems(): bool
    {
        return $this->totalProcessed > 0;
    }

    /**
     * Get success rate as percentage
     */
    public function getSuccessRate(): float
    {
        if ($this->totalProcessed === 0) {
            return 0.0;
        }

        $successful = $this->synced + $this->updated;
        return ($successful / $this->totalProcessed) * 100;
    }

    /**
     * Get error rate as percentage
     */
    public function getErrorRate(): float
    {
        if ($this->totalProcessed === 0) {
            return 0.0;
        }

        return ($this->errors / $this->totalProcessed) * 100;
    }

    /**
     * Get processing speed (items per second)
     */
    public function getProcessingSpeed(): float
    {
        if ($this->executionTime === 0.0) {
            return 0.0;
        }

        return $this->totalProcessed / $this->executionTime;
    }

    /**
     * Get formatted execution time
     */
    public function getFormattedExecutionTime(): string
    {
        if ($this->executionTime < 60) {
            return number_format($this->executionTime, 2) . ' seconds';
        }

        $minutes = floor($this->executionTime / 60);
        $seconds = $this->executionTime % 60;
        
        return "{$minutes}m " . number_format($seconds, 2) . 's';
    }

    /**
     * Get summary statistics
     */
    public function getSummary(): array
    {
        return [
            'success' => $this->success,
            'entity_type' => $this->entityType,
            'totals' => [
                'synced' => $this->synced,
                'updated' => $this->updated,
                'skipped' => $this->skipped,
                'errors' => $this->errors,
                'total_processed' => $this->totalProcessed,
            ],
            'rates' => [
                'success_rate' => $this->getSuccessRate(),
                'error_rate' => $this->getErrorRate(),
                'processing_speed' => $this->getProcessingSpeed(),
            ],
            'timing' => [
                'started_at' => $this->startedAt,
                'completed_at' => $this->completedAt,
                'execution_time' => $this->executionTime,
                'formatted_execution_time' => $this->getFormattedExecutionTime(),
            ],
            'context' => $this->context,
            'has_errors' => $this->hasErrors(),
        ];
    }

    /**
     * Get detailed report
     */
    public function getDetailedReport(): array
    {
        $report = $this->getSummary();
        
        $report['processed_items'] = $this->processedItems;
        $report['error_items'] = $this->errorItems;
        $report['memory_stats'] = $this->memoryStats;
        $report['rate_limit_stats'] = $this->rateLimitStats;
        $report['health_stats'] = $this->healthStats;
        $report['progress_data'] = $this->progressData;
        $report['metadata'] = $this->metadata;
        
        if ($this->errorMessage) {
            $report['error_message'] = $this->errorMessage;
        }
        
        if ($this->exception) {
            $report['exception'] = $this->exception;
        }
        
        return $report;
    }

    /**
     * Convert to log-friendly format
     */
    public function toLogFormat(): array
    {
        $log = [
            'sync_result' => [
                'success' => $this->success,
                'entity_type' => $this->entityType,
                'synced' => $this->synced,
                'updated' => $this->updated,
                'skipped' => $this->skipped,
                'errors' => $this->errors,
                'total_processed' => $this->totalProcessed,
                'execution_time' => $this->executionTime,
                'success_rate' => $this->getSuccessRate(),
                'processing_speed' => $this->getProcessingSpeed(),
                'context' => $this->context,
            ]
        ];

        if ($this->errorMessage) {
            $log['sync_result']['error_message'] = $this->errorMessage;
        }

        if (!empty($this->memoryStats)) {
            $log['memory_stats'] = $this->memoryStats;
        }

        if (!empty($this->rateLimitStats)) {
            $log['rate_limit_stats'] = $this->rateLimitStats;
        }

        if (!empty($this->metadata)) {
            $log['metadata'] = $this->metadata;
        }

        return $log;
    }

    /**
     * Merge with another result
     */
    public function merge(SyncResult $other): self
    {
        if ($this->entityType !== $other->entityType) {
            throw new \InvalidArgumentException('Cannot merge results for different entity types');
        }

        return new self(
            success: $this->success && $other->success,
            entityType: $this->entityType,
            synced: $this->synced + $other->synced,
            updated: $this->updated + $other->updated,
            skipped: $this->skipped + $other->skipped,
            errors: $this->errors + $other->errors,
            executionTime: $this->executionTime + $other->executionTime,
            processedItems: array_merge($this->processedItems, $other->processedItems),
            errorItems: array_merge($this->errorItems, $other->errorItems),
            memoryStats: array_merge($this->memoryStats, $other->memoryStats),
            rateLimitStats: array_merge($this->rateLimitStats, $other->rateLimitStats),
            healthStats: array_merge($this->healthStats, $other->healthStats),
            metadata: array_merge($this->metadata, $other->metadata),
            context: $this->context,
            startedAt: $this->startedAt,
            completedAt: $other->completedAt,
            progressData: array_merge($this->progressData, $other->progressData)
        );
    }
}
