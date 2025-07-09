<?php

namespace Keggermont\LaravelPipedrive\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PipedriveWebhookReceived
{
    use Dispatchable, SerializesModels;

    public array $webhookData;
    public string $action;
    public string $object;
    public int $objectId;
    public array $meta;
    public ?array $current;
    public ?array $previous;

    /**
     * Create a new event instance.
     */
    public function __construct(array $webhookData)
    {
        $this->webhookData = $webhookData;
        $this->meta = $webhookData['meta'] ?? [];
        $this->action = $this->meta['action'] ?? '';
        $this->object = $this->meta['object'] ?? '';
        $this->objectId = $this->meta['id'] ?? 0;
        $this->current = $webhookData['current'] ?? null;
        $this->previous = $webhookData['previous'] ?? null;
    }

    /**
     * Check if this is a create event
     */
    public function isCreate(): bool
    {
        return $this->action === 'added';
    }

    /**
     * Check if this is an update event
     */
    public function isUpdate(): bool
    {
        return $this->action === 'updated';
    }

    /**
     * Check if this is a delete event
     */
    public function isDelete(): bool
    {
        return $this->action === 'deleted';
    }

    /**
     * Check if this is a merge event
     */
    public function isMerge(): bool
    {
        return $this->action === 'merged';
    }

    /**
     * Check if this event is for a specific object type
     */
    public function isObjectType(string $objectType): bool
    {
        return $this->object === $objectType;
    }

    /**
     * Get the change source (app or api)
     */
    public function getChangeSource(): ?string
    {
        return $this->meta['change_source'] ?? null;
    }

    /**
     * Check if this change came from the Pipedrive app
     */
    public function isFromApp(): bool
    {
        return $this->getChangeSource() === 'app';
    }

    /**
     * Check if this change came from the API
     */
    public function isFromApi(): bool
    {
        return $this->getChangeSource() === 'api';
    }

    /**
     * Get the user ID who triggered the change
     */
    public function getUserId(): ?int
    {
        return $this->meta['user_id'] ?? null;
    }

    /**
     * Get the company ID where the change occurred
     */
    public function getCompanyId(): ?int
    {
        return $this->meta['company_id'] ?? null;
    }

    /**
     * Check if this is a bulk update
     */
    public function isBulkUpdate(): bool
    {
        return $this->meta['is_bulk_update'] ?? false;
    }

    /**
     * Get the retry count
     */
    public function getRetryCount(): int
    {
        return $this->webhookData['retry'] ?? 0;
    }

    /**
     * Check if this is a retry attempt
     */
    public function isRetry(): bool
    {
        return $this->getRetryCount() > 0;
    }
}
