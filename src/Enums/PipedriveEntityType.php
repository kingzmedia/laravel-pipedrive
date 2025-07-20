<?php

namespace Skeylup\LaravelPipedrive\Enums;

enum PipedriveEntityType: string
{
    case DEALS = 'deals';
    case PERSONS = 'persons';
    case ORGANIZATIONS = 'organizations';
    case ACTIVITIES = 'activities';
    case PRODUCTS = 'products';
    case FILES = 'files';
    case NOTES = 'notes';
    case USERS = 'users';
    case PIPELINES = 'pipelines';
    case STAGES = 'stages';
    case GOALS = 'goals';

    /**
     * Get the display name for the entity type
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::DEALS => 'Deals',
            self::PERSONS => 'Persons',
            self::ORGANIZATIONS => 'Organizations',
            self::ACTIVITIES => 'Activities',
            self::PRODUCTS => 'Products',
            self::FILES => 'Files',
            self::NOTES => 'Notes',
            self::USERS => 'Users',
            self::PIPELINES => 'Pipelines',
            self::STAGES => 'Stages',
            self::GOALS => 'Goals',
        };
    }

    /**
     * Get the corresponding Pipedrive model class
     */
    public function getModelClass(): string
    {
        return match ($this) {
            self::DEALS => \Skeylup\LaravelPipedrive\Models\PipedriveDeal::class,
            self::PERSONS => \Skeylup\LaravelPipedrive\Models\PipedrivePerson::class,
            self::ORGANIZATIONS => \Skeylup\LaravelPipedrive\Models\PipedriveOrganization::class,
            self::ACTIVITIES => \Skeylup\LaravelPipedrive\Models\PipedriveActivity::class,
            self::PRODUCTS => \Skeylup\LaravelPipedrive\Models\PipedriveProduct::class,
            self::FILES => \Skeylup\LaravelPipedrive\Models\PipedriveFile::class,
            self::NOTES => \Skeylup\LaravelPipedrive\Models\PipedriveNote::class,
            self::USERS => \Skeylup\LaravelPipedrive\Models\PipedriveUser::class,
            self::PIPELINES => \Skeylup\LaravelPipedrive\Models\PipedrivePipeline::class,
            self::STAGES => \Skeylup\LaravelPipedrive\Models\PipedriveStage::class,
            self::GOALS => \Skeylup\LaravelPipedrive\Models\PipedriveGoal::class,
        };
    }

    /**
     * Get the API endpoint for this entity type
     */
    public function getApiEndpoint(): string
    {
        return $this->value;
    }

    /**
     * Get all available entity types
     */
    public static function all(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Get all entity types with their display names
     */
    public static function allWithNames(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->getDisplayName();
        }

        return $result;
    }

    /**
     * Check if a string is a valid entity type
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all());
    }

    /**
     * Create from string with validation
     */
    public static function fromString(string $type): self
    {
        if (! self::isValid($type)) {
            throw new \InvalidArgumentException("Invalid Pipedrive entity type: {$type}");
        }

        return self::from($type);
    }

    /**
     * Get suggested entity types for common Laravel model names
     */
    public static function getSuggestedForModel(string $modelName): array
    {
        $modelName = strtolower(class_basename($modelName));

        return match (true) {
            str_contains($modelName, 'order') => [self::DEALS],
            str_contains($modelName, 'sale') => [self::DEALS],
            str_contains($modelName, 'deal') => [self::DEALS],
            str_contains($modelName, 'customer') => [self::PERSONS],
            str_contains($modelName, 'client') => [self::PERSONS],
            str_contains($modelName, 'user') => [self::PERSONS],
            str_contains($modelName, 'person') => [self::PERSONS],
            str_contains($modelName, 'contact') => [self::PERSONS],
            str_contains($modelName, 'company') => [self::ORGANIZATIONS],
            str_contains($modelName, 'organization') => [self::ORGANIZATIONS],
            str_contains($modelName, 'business') => [self::ORGANIZATIONS],
            str_contains($modelName, 'task') => [self::ACTIVITIES],
            str_contains($modelName, 'activity') => [self::ACTIVITIES],
            str_contains($modelName, 'event') => [self::ACTIVITIES],
            str_contains($modelName, 'product') => [self::PRODUCTS],
            str_contains($modelName, 'item') => [self::PRODUCTS],
            str_contains($modelName, 'service') => [self::PRODUCTS],
            str_contains($modelName, 'file') => [self::FILES],
            str_contains($modelName, 'document') => [self::FILES],
            str_contains($modelName, 'attachment') => [self::FILES],
            str_contains($modelName, 'note') => [self::NOTES],
            str_contains($modelName, 'comment') => [self::NOTES],
            default => [self::DEALS, self::PERSONS], // Default suggestions
        };
    }
}
