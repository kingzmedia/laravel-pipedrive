<?php

namespace Skeylup\LaravelPipedrive\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SyncPipedriveCustomFieldsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?string $entityType;

    protected bool $force;

    protected bool $fullData;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(?string $entityType = null, bool $force = true, bool $fullData = false)
    {
        $this->entityType = $entityType;
        $this->force = $force;
        $this->fullData = $fullData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            Log::info('Starting custom fields sync job', [
                'entity_type' => $this->entityType,
                'force' => $this->force,
                'full_data' => $this->fullData,
                'job_id' => $this->job?->getJobId(),
                'attempt' => $this->attempts(),
            ]);

            // Build command arguments
            $arguments = [];

            if ($this->entityType) {
                $arguments['--entity'] = $this->entityType;
            }

            if ($this->force) {
                $arguments['--force'] = true;
            }

            if ($this->fullData) {
                $arguments['--full-data'] = true;
            }

            // Execute the sync command
            $exitCode = Artisan::call('pipedrive:sync-custom-fields', $arguments);

            $executionTime = microtime(true) - $startTime;

            if ($exitCode === 0) {
                Log::info('Custom fields sync job completed successfully', [
                    'entity_type' => $this->entityType,
                    'execution_time' => round($executionTime, 2),
                    'job_id' => $this->job?->getJobId(),
                    'attempt' => $this->attempts(),
                ]);
            } else {
                throw new \Exception("Custom fields sync command failed with exit code: {$exitCode}");
            }

        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            Log::error('Custom fields sync job failed', [
                'entity_type' => $this->entityType,
                'error' => $e->getMessage(),
                'execution_time' => round($executionTime, 2),
                'job_id' => $this->job?->getJobId(),
                'attempt' => $this->attempts(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw the exception to trigger job failure
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Custom fields sync job failed permanently', [
            'entity_type' => $this->entityType,
            'error' => $exception->getMessage(),
            'job_id' => $this->job?->getJobId(),
            'attempts' => $this->attempts(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        $tags = ['pipedrive', 'custom-fields', 'sync'];

        if ($this->entityType) {
            $tags[] = "entity:{$this->entityType}";
        }

        return $tags;
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }
}
