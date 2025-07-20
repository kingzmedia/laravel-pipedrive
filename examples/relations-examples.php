<?php

/**
 * Exemples pratiques d'utilisation des relations Pipedrive
 */

use Skeylup\LaravelPipedrive\Models\PipedriveDeal;
use Skeylup\LaravelPipedrive\Models\PipedriveOrganization;
use Skeylup\LaravelPipedrive\Models\PipedriveUser;

// Exemple 1: Récupérer tous les deals d'un utilisateur avec leurs informations complètes
function getUserDealsWithDetails($userId)
{
    $user = PipedriveUser::with([
        'deals.person',
        'deals.organization',
        'deals.stage.pipeline',
        'deals.activities' => function ($query) {
            $query->where('done', false)->orderBy('due_date');
        },
    ])->where('pipedrive_id', $userId)->first();

    if (! $user) {
        return null;
    }

    $result = [
        'user' => $user->name,
        'deals' => [],
    ];

    foreach ($user->deals as $deal) {
        $result['deals'][] = [
            'title' => $deal->title,
            'value' => $deal->value,
            'currency' => $deal->currency,
            'person' => $deal->person ? $deal->person->name : null,
            'organization' => $deal->organization ? $deal->organization->name : null,
            'stage' => $deal->stage ? $deal->stage->name : null,
            'pipeline' => $deal->stage && $deal->stage->pipeline ? $deal->stage->pipeline->name : null,
            'pending_activities' => $deal->activities->count(),
        ];
    }

    return $result;
}

// Exemple 2: Dashboard d'activités par organisation
function getOrganizationActivityDashboard($orgId)
{
    $org = PipedriveOrganization::with([
        'activities.user',
        'activities.deal',
        'deals.stage',
        'persons.activities',
    ])->where('pipedrive_id', $orgId)->first();

    if (! $org) {
        return null;
    }

    // Activités directes de l'organisation
    $directActivities = $org->activities;

    // Activités via les personnes de l'organisation
    $personActivities = collect();
    foreach ($org->persons as $person) {
        $personActivities = $personActivities->merge($person->activities);
    }

    $allActivities = $directActivities->merge($personActivities)->unique('pipedrive_id');

    return [
        'organization' => $org->name,
        'total_activities' => $allActivities->count(),
        'completed_activities' => $allActivities->where('done', true)->count(),
        'overdue_activities' => $allActivities->where('done', false)
            ->where('due_date', '<', now())->count(),
        'active_deals' => $org->deals->where('status', 'open')->count(),
        'total_deal_value' => $org->deals->where('status', 'open')->sum('value'),
        'recent_activities' => $allActivities->sortByDesc('due_date')->take(5)->map(function ($activity) {
            return [
                'subject' => $activity->subject,
                'type' => $activity->type,
                'due_date' => $activity->due_date,
                'assigned_to' => $activity->user ? $activity->user->name : null,
                'deal' => $activity->deal ? $activity->deal->title : null,
                'done' => $activity->done,
            ];
        })->values(),
    ];
}

// Exemple 3: Rapport de performance par pipeline
function getPipelinePerformanceReport($pipelineId)
{
    $pipeline = PipedrivePipeline::with([
        'stages.deals' => function ($query) {
            $query->where('status', 'open');
        },
    ])->where('pipedrive_id', $pipelineId)->first();

    if (! $pipeline) {
        return null;
    }

    $report = [
        'pipeline' => $pipeline->name,
        'stages' => [],
        'totals' => [
            'deals' => 0,
            'value' => 0,
        ],
    ];

    foreach ($pipeline->stages as $stage) {
        $stageDeals = $stage->deals;
        $stageValue = $stageDeals->sum('value');
        $dealCount = $stageDeals->count();

        $report['stages'][] = [
            'name' => $stage->name,
            'order' => $stage->order_nr,
            'probability' => $stage->deal_probability,
            'deals_count' => $dealCount,
            'total_value' => $stageValue,
            'weighted_value' => $stageValue * ($stage->deal_probability / 100),
            'average_deal_value' => $dealCount > 0 ? $stageValue / $dealCount : 0,
        ];

        $report['totals']['deals'] += $dealCount;
        $report['totals']['value'] += $stageValue;
    }

    return $report;
}

// Exemple 4: Recherche avancée avec relations
function searchDealsWithFilters($filters = [])
{
    $query = PipedriveDeal::with(['user', 'person', 'organization', 'stage']);

    // Filtrer par nom de personne
    if (isset($filters['person_name'])) {
        $query->whereHas('person', function ($q) use ($filters) {
            $q->where('name', 'like', '%'.$filters['person_name'].'%');
        });
    }

    // Filtrer par organisation
    if (isset($filters['organization_name'])) {
        $query->whereHas('organization', function ($q) use ($filters) {
            $q->where('name', 'like', '%'.$filters['organization_name'].'%');
        });
    }

    // Filtrer par utilisateur assigné
    if (isset($filters['user_name'])) {
        $query->whereHas('user', function ($q) use ($filters) {
            $q->where('name', 'like', '%'.$filters['user_name'].'%');
        });
    }

    // Filtrer par stage
    if (isset($filters['stage_name'])) {
        $query->whereHas('stage', function ($q) use ($filters) {
            $q->where('name', 'like', '%'.$filters['stage_name'].'%');
        });
    }

    // Filtrer par valeur minimale
    if (isset($filters['min_value'])) {
        $query->where('value', '>=', $filters['min_value']);
    }

    return $query->get()->map(function ($deal) {
        return [
            'title' => $deal->title,
            'value' => $deal->value,
            'currency' => $deal->currency,
            'person' => $deal->person ? $deal->person->name : null,
            'organization' => $deal->organization ? $deal->organization->name : null,
            'user' => $deal->user ? $deal->user->name : null,
            'stage' => $deal->stage ? $deal->stage->name : null,
        ];
    });
}

// Exemple 5: Statistiques d'activités par utilisateur
function getUserActivityStats($userId)
{
    $user = PipedriveUser::with([
        'activities' => function ($query) {
            $query->orderBy('due_date', 'desc');
        },
    ])->where('pipedrive_id', $userId)->first();

    if (! $user) {
        return null;
    }

    $activities = $user->activities;
    $now = now();

    return [
        'user' => $user->name,
        'total_activities' => $activities->count(),
        'completed' => $activities->where('done', true)->count(),
        'pending' => $activities->where('done', false)->count(),
        'overdue' => $activities->where('done', false)
            ->where('due_date', '<', $now)->count(),
        'today' => $activities->where('done', false)
            ->whereBetween('due_date', [$now->startOfDay(), $now->endOfDay()])->count(),
        'this_week' => $activities->where('done', false)
            ->whereBetween('due_date', [$now->startOfWeek(), $now->endOfWeek()])->count(),
        'by_type' => $activities->groupBy('type')->map(function ($group) {
            return $group->count();
        }),
        'completion_rate' => $activities->count() > 0
            ? round(($activities->where('done', true)->count() / $activities->count()) * 100, 2)
            : 0,
    ];
}

// Exemple d'utilisation
/*
// Récupérer les deals d'un utilisateur
$userDeals = getUserDealsWithDetails(123456);

// Dashboard d'une organisation
$orgDashboard = getOrganizationActivityDashboard(789012);

// Rapport de pipeline
$pipelineReport = getPipelinePerformanceReport(345678);

// Recherche avancée
$searchResults = searchDealsWithFilters([
    'person_name' => 'John',
    'min_value' => 1000,
    'stage_name' => 'Negotiation'
]);

// Statistiques utilisateur
$userStats = getUserActivityStats(123456);
*/
