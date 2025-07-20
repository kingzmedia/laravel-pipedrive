<?php

/**
 * Exemples pratiques d'utilisation des webhooks Pipedrive
 */

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Skeylup\LaravelPipedrive\Events\PipedriveWebhookReceived;

// Exemple 1: Listener pour notifications automatiques
class DealStageChangeListener
{
    public function handle(PipedriveWebhookReceived $event)
    {
        // Seulement pour les deals mis à jour
        if (! $event->isUpdate() || ! $event->isObjectType('deal')) {
            return;
        }

        $current = $event->current;
        $previous = $event->previous;

        // Détecter changement de stage
        if ($current['stage_id'] !== $previous['stage_id']) {
            $this->notifyStageChange($current, $previous, $event->getUserId());
        }

        // Détecter deal gagné
        if ($current['status'] === 'won' && $previous['status'] !== 'won') {
            $this->notifyDealWon($current);
        }

        // Détecter deal perdu
        if ($current['status'] === 'lost' && $previous['status'] !== 'lost') {
            $this->notifyDealLost($current, $previous);
        }
    }

    protected function notifyStageChange($current, $previous, $userId)
    {
        // Envoyer notification à l'équipe
        $message = "Deal '{$current['title']}' moved from stage {$previous['stage_id']} to {$current['stage_id']}";

        // Slack notification
        \Slack::to('#sales')->send($message);

        // Email notification
        Mail::to('sales@company.com')->send(new DealStageChangedMail($current, $previous));
    }

    protected function notifyDealWon($deal)
    {
        $value = number_format($deal['value'], 2);
        $currency = $deal['currency'];

        $message = "🎉 Deal WON! '{$deal['title']}' - {$value} {$currency}";

        // Celebration notification
        \Slack::to('#sales')->send($message);

        // Update CRM metrics
        $this->updateSalesMetrics($deal);
    }

    protected function notifyDealLost($current, $previous)
    {
        $reason = $current['lost_reason'] ?? 'No reason specified';

        $message = "😞 Deal lost: '{$current['title']}' - Reason: {$reason}";

        \Slack::to('#sales')->send($message);
    }
}

// Exemple 2: Synchronisation avec système externe
class ExternalSystemSyncListener
{
    public function handle(PipedriveWebhookReceived $event)
    {
        // Synchroniser les personnes avec le CRM externe
        if ($event->isObjectType('person')) {
            $this->syncPersonToExternalCRM($event);
        }

        // Synchroniser les organisations avec le système comptable
        if ($event->isObjectType('organization')) {
            $this->syncOrganizationToAccounting($event);
        }

        // Créer des tâches automatiques pour nouveaux deals
        if ($event->isCreate() && $event->isObjectType('deal')) {
            $this->createAutomaticTasks($event->current);
        }
    }

    protected function syncPersonToExternalCRM($event)
    {
        $person = $event->current;

        if ($event->isCreate()) {
            // Créer dans le CRM externe
            ExternalCRM::createContact([
                'pipedrive_id' => $person['id'],
                'name' => $person['name'],
                'email' => $person['email'][0]['value'] ?? null,
                'phone' => $person['phone'][0]['value'] ?? null,
            ]);
        }

        if ($event->isUpdate()) {
            // Mettre à jour dans le CRM externe
            ExternalCRM::updateContact($person['id'], [
                'name' => $person['name'],
                'email' => $person['email'][0]['value'] ?? null,
                'phone' => $person['phone'][0]['value'] ?? null,
            ]);
        }

        if ($event->isDelete()) {
            // Supprimer du CRM externe
            ExternalCRM::deleteContact($event->objectId);
        }
    }

    protected function createAutomaticTasks($deal)
    {
        // Créer des tâches automatiques pour nouveau deal
        $tasks = [
            [
                'subject' => 'Send welcome email',
                'type' => 'email',
                'due_date' => now()->addHours(2),
            ],
            [
                'subject' => 'Schedule discovery call',
                'type' => 'call',
                'due_date' => now()->addDays(1),
            ],
            [
                'subject' => 'Send proposal',
                'type' => 'task',
                'due_date' => now()->addDays(3),
            ],
        ];

        foreach ($tasks as $task) {
            CreatePipedriveActivityJob::dispatch($deal['id'], $task);
        }
    }
}

// Exemple 3: Analytics et reporting en temps réel
class RealTimeAnalyticsListener
{
    public function handle(PipedriveWebhookReceived $event)
    {
        // Mettre à jour les métriques en temps réel
        if ($event->isObjectType('deal')) {
            $this->updateDealMetrics($event);
        }

        if ($event->isObjectType('activity')) {
            $this->updateActivityMetrics($event);
        }
    }

    protected function updateDealMetrics($event)
    {
        $deal = $event->current;

        if ($event->isCreate()) {
            // Nouveau deal créé
            Redis::incr('deals:created:today');
            Redis::incrByFloat('deals:value:today', $deal['value'] ?? 0);
        }

        if ($event->isUpdate()) {
            $previous = $event->previous;

            // Deal gagné
            if ($deal['status'] === 'won' && $previous['status'] !== 'won') {
                Redis::incr('deals:won:today');
                Redis::incrByFloat('deals:won:value:today', $deal['value'] ?? 0);

                // Mettre à jour le dashboard en temps réel
                broadcast(new DealWonEvent($deal));
            }

            // Changement de valeur
            if ($deal['value'] !== $previous['value']) {
                $difference = ($deal['value'] ?? 0) - ($previous['value'] ?? 0);
                Redis::incrByFloat('deals:value:today', $difference);
            }
        }
    }

    protected function updateActivityMetrics($event)
    {
        $activity = $event->current;

        if ($event->isUpdate() && $activity['done'] && ! $event->previous['done']) {
            // Activité complétée
            Redis::incr('activities:completed:today');
            Redis::incr("activities:completed:user:{$activity['user_id']}:today");

            // Mettre à jour le leaderboard
            $this->updateActivityLeaderboard($activity['user_id']);
        }
    }
}

// Exemple 4: Validation et nettoyage des données
class DataValidationListener
{
    public function handle(PipedriveWebhookReceived $event)
    {
        // Valider les données des personnes
        if ($event->isObjectType('person') && ($event->isCreate() || $event->isUpdate())) {
            $this->validatePersonData($event->current);
        }

        // Nettoyer les données des organisations
        if ($event->isObjectType('organization') && ($event->isCreate() || $event->isUpdate())) {
            $this->cleanOrganizationData($event->current);
        }
    }

    protected function validatePersonData($person)
    {
        $issues = [];

        // Vérifier l'email
        if (! empty($person['email']) && ! filter_var($person['email'][0]['value'], FILTER_VALIDATE_EMAIL)) {
            $issues[] = 'Invalid email format';
        }

        // Vérifier le téléphone
        if (! empty($person['phone']) && ! $this->isValidPhone($person['phone'][0]['value'])) {
            $issues[] = 'Invalid phone format';
        }

        // Vérifier les champs obligatoires
        if (empty($person['name'])) {
            $issues[] = 'Missing name';
        }

        if (! empty($issues)) {
            // Notifier l'équipe des problèmes de données
            \Slack::to('#data-quality')->send(
                "Data quality issues for person {$person['id']}: ".implode(', ', $issues)
            );
        }
    }

    protected function cleanOrganizationData($organization)
    {
        // Standardiser le nom de l'organisation
        $cleanName = $this->standardizeCompanyName($organization['name']);

        if ($cleanName !== $organization['name']) {
            // Mettre à jour via l'API Pipedrive
            UpdatePipedriveOrganizationJob::dispatch($organization['id'], [
                'name' => $cleanName,
            ]);
        }
    }

    protected function standardizeCompanyName($name)
    {
        // Supprimer les suffixes communs et standardiser
        $suffixes = ['Inc.', 'LLC', 'Ltd.', 'Corp.', 'Co.'];
        $name = trim($name);

        foreach ($suffixes as $suffix) {
            if (str_ends_with($name, $suffix)) {
                $name = trim(str_replace($suffix, '', $name));
                break;
            }
        }

        return $name;
    }
}

// Exemple 5: Intégration avec système de facturation
class BillingIntegrationListener
{
    public function handle(PipedriveWebhookReceived $event)
    {
        // Créer une facture quand un deal est gagné
        if ($event->isUpdate() && $event->isObjectType('deal')) {
            $deal = $event->current;
            $previous = $event->previous;

            if ($deal['status'] === 'won' && $previous['status'] !== 'won') {
                $this->createInvoice($deal);
            }
        }
    }

    protected function createInvoice($deal)
    {
        // Récupérer les détails du deal
        $person = PipedrivePerson::where('pipedrive_id', $deal['person_id'])->first();
        $organization = PipedriveOrganization::where('pipedrive_id', $deal['org_id'])->first();

        // Créer la facture dans le système de facturation
        $invoice = BillingSystem::createInvoice([
            'deal_id' => $deal['id'],
            'customer_name' => $organization ? $organization->name : $person->name,
            'customer_email' => $person->email ?? null,
            'amount' => $deal['value'],
            'currency' => $deal['currency'],
            'description' => $deal['title'],
            'due_date' => now()->addDays(30),
        ]);

        // Ajouter une note au deal avec le lien de la facture
        CreatePipedriveNoteJob::dispatch($deal['id'], [
            'content' => "Invoice created: {$invoice->number} - {$invoice->url}",
        ]);
    }
}

// Configuration dans EventServiceProvider
/*
protected $listen = [
    PipedriveWebhookReceived::class => [
        DealStageChangeListener::class,
        ExternalSystemSyncListener::class,
        RealTimeAnalyticsListener::class,
        DataValidationListener::class,
        BillingIntegrationListener::class,
    ],
];
*/
