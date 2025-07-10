<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Keggermont\LaravelPipedrive\Events\{
    PipedriveEntityCreated,
    PipedriveEntityUpdated,
    PipedriveEntityDeleted
};

/**
 * Example listener for Pipedrive entity created events
 */
class PipedriveEntityCreatedListener
{
    public function handle(PipedriveEntityCreated $event): void
    {
        Log::info('Pipedrive entity created', $event->getSummary());

        // Example: Send notification when a new deal is created
        if ($event->isDeal()) {
            $this->handleNewDeal($event);
        }

        // Example: Create welcome task when a new person is created
        if ($event->isPerson()) {
            $this->handleNewPerson($event);
        }

        // Example: Setup organization in external system
        if ($event->isOrganization()) {
            $this->handleNewOrganization($event);
        }
    }

    private function handleNewDeal($event): void
    {
        $deal = $event->entity;
        
        // Only process deals from webhooks (real-time)
        if ($event->isFromWebhook()) {
            // Send notification to sales team
            Mail::to('sales@company.com')->send(new NewDealNotification($deal));
            
            // Update dashboard metrics
            Cache::increment('deals_created_today');
            Cache::increment('deals_value_today', $deal->value ?? 0);
        }
    }

    private function handleNewPerson($event): void
    {
        $person = $event->entity;
        
        // Create welcome activity
        CreateWelcomeActivityJob::dispatch($person->pipedrive_id);
        
        // Add to mailing list
        AddToMailingListJob::dispatch($person->email);
    }

    private function handleNewOrganization($event): void
    {
        $organization = $event->entity;
        
        // Sync with external CRM
        SyncOrganizationToExternalCrmJob::dispatch($organization);
    }
}

/**
 * Example listener for Pipedrive entity updated events
 */
class PipedriveEntityUpdatedListener
{
    public function handle(PipedriveEntityUpdated $event): void
    {
        Log::info('Pipedrive entity updated', $event->getSummary());

        // Example: Handle deal stage changes
        if ($event->isDeal() && $event->statusChanged()) {
            $this->handleDealStatusChange($event);
        }

        // Example: Handle deal value changes
        if ($event->isDeal() && $event->valueChanged()) {
            $this->handleDealValueChange($event);
        }

        // Example: Handle owner changes
        if ($event->ownerChanged()) {
            $this->handleOwnerChange($event);
        }

        // Example: Handle person email changes
        if ($event->isPerson() && $event->hasChanged('email')) {
            $this->handlePersonEmailChange($event);
        }
    }

    private function handleDealStatusChange($event): void
    {
        $deal = $event->entity;
        $oldStatus = $event->getOldValue('status');
        $newStatus = $event->getNewValue('status');

        Log::info("Deal {$deal->pipedrive_id} status changed from {$oldStatus} to {$newStatus}");

        // Deal won
        if ($newStatus === 'won') {
            // Send congratulations email
            Mail::to($deal->user->email ?? 'sales@company.com')
                ->send(new DealWonNotification($deal));
            
            // Create invoice
            CreateInvoiceJob::dispatch($deal);
            
            // Update metrics
            Cache::increment('deals_won_today');
            Cache::increment('deals_won_value_today', $deal->value ?? 0);
        }

        // Deal lost
        if ($newStatus === 'lost') {
            // Schedule follow-up
            ScheduleFollowUpJob::dispatch($deal, '+30 days');
        }
    }

    private function handleDealValueChange($event): void
    {
        $deal = $event->entity;
        $oldValue = $event->getOldValue('value') ?? 0;
        $newValue = $event->getNewValue('value') ?? 0;
        $difference = $newValue - $oldValue;

        Log::info("Deal {$deal->pipedrive_id} value changed by {$difference}");

        // Update metrics
        Cache::increment('deals_value_change_today', $difference);

        // Notify if significant change
        if (abs($difference) > 10000) {
            Mail::to('management@company.com')
                ->send(new SignificantDealValueChangeNotification($deal, $oldValue, $newValue));
        }
    }

    private function handleOwnerChange($event): void
    {
        $entity = $event->entity;
        $oldOwnerId = $event->getOldValue('user_id') ?? $event->getOldValue('owner_id');
        $newOwnerId = $event->getNewValue('user_id') ?? $event->getNewValue('owner_id');

        Log::info("Entity {$event->getEntityName()} {$entity->pipedrive_id} owner changed from {$oldOwnerId} to {$newOwnerId}");

        // Send notification to new owner
        NotifyNewOwnerJob::dispatch($entity, $newOwnerId);
    }

    private function handlePersonEmailChange($event): void
    {
        $person = $event->entity;
        $oldEmail = $event->getOldValue('email');
        $newEmail = $event->getNewValue('email');

        Log::info("Person {$person->pipedrive_id} email changed from {$oldEmail} to {$newEmail}");

        // Update mailing list
        UpdateMailingListEmailJob::dispatch($oldEmail, $newEmail);
    }
}

/**
 * Example listener for Pipedrive entity deleted events
 */
class PipedriveEntityDeletedListener
{
    public function handle(PipedriveEntityDeleted $event): void
    {
        Log::info('Pipedrive entity deleted', $event->getSummary());

        // Example: Handle deal deletion
        if ($event->isDeal()) {
            $this->handleDealDeletion($event);
        }

        // Example: Handle person deletion
        if ($event->isPerson()) {
            $this->handlePersonDeletion($event);
        }

        // Example: Clean up related data
        $this->cleanupRelatedData($event);
    }

    private function handleDealDeletion($event): void
    {
        $pipedriveId = $event->getPipedriveId();
        $dealValue = $event->getDeletedValue();

        Log::info("Deal {$pipedriveId} deleted (value: {$dealValue})");

        // Update metrics
        if ($dealValue) {
            Cache::decrement('total_pipeline_value', $dealValue);
        }

        // Clean up related activities
        CleanupDealActivitiesJob::dispatch($pipedriveId);
    }

    private function handlePersonDeletion($event): void
    {
        $pipedriveId = $event->getPipedriveId();
        $personName = $event->getEntityTitle();

        Log::info("Person {$pipedriveId} ({$personName}) deleted");

        // Remove from mailing lists
        $email = $event->getEntityData('email');
        if ($email) {
            RemoveFromMailingListJob::dispatch($email);
        }
    }

    private function cleanupRelatedData($event): void
    {
        // Clean up entity links
        CleanupEntityLinksJob::dispatch($event->getEntityName(), $event->getPipedriveId());
        
        // Clean up cached data
        Cache::forget("pipedrive_{$event->getEntityName()}_{$event->getPipedriveId()}");
    }
}

/**
 * Example analytics listener that tracks all events
 */
class PipedriveAnalyticsListener
{
    public function handleCreated(PipedriveEntityCreated $event): void
    {
        $this->trackEvent('created', $event->getEntityName(), $event->getSummary());
    }

    public function handleUpdated(PipedriveEntityUpdated $event): void
    {
        $this->trackEvent('updated', $event->getEntityName(), $event->getSummary());
    }

    public function handleDeleted(PipedriveEntityDeleted $event): void
    {
        $this->trackEvent('deleted', $event->getEntityName(), $event->getSummary());
    }

    private function trackEvent(string $action, string $entityType, array $data): void
    {
        // Send to analytics service
        Analytics::track("pipedrive_{$entityType}_{$action}", $data);
        
        // Update daily counters
        $today = now()->format('Y-m-d');
        Cache::increment("pipedrive_events_{$today}");
        Cache::increment("pipedrive_{$entityType}_{$action}_{$today}");
    }
}

/**
 * Configuration dans EventServiceProvider
 */
/*
protected $listen = [
    PipedriveEntityCreated::class => [
        PipedriveEntityCreatedListener::class,
        'App\Listeners\PipedriveAnalyticsListener@handleCreated',
    ],
    PipedriveEntityUpdated::class => [
        PipedriveEntityUpdatedListener::class,
        'App\Listeners\PipedriveAnalyticsListener@handleUpdated',
    ],
    PipedriveEntityDeleted::class => [
        PipedriveEntityDeletedListener::class,
        'App\Listeners\PipedriveAnalyticsListener@handleDeleted',
    ],
];
*/
