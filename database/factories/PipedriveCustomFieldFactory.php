<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Keggermont\LaravelPipedrive\Models\PipedriveCustomField;

class PipedriveCustomFieldFactory extends Factory
{
    protected $model = PipedriveCustomField::class;

    public function definition(): array
    {
        return [
            'pipedrive_id' => $this->faker->numberBetween(1, 9999),
            'name' => $this->faker->words(2, true),
            'key' => $this->generateCustomFieldKey(),
            'field_type' => $this->faker->randomElement(['varchar', 'text', 'int', 'decimal', 'date', 'enum', 'user', 'org']),
            'entity_type' => $this->faker->randomElement(['deal', 'person', 'organization', 'product', 'activity']),
            'active_flag' => true,
            'pipedrive_data' => [
                'id' => $this->faker->numberBetween(1, 9999),
                'name' => $this->faker->words(2, true),
                'field_type' => $this->faker->randomElement(['varchar', 'text', 'int', 'decimal', 'date', 'enum', 'user', 'org']),
                'active_flag' => true,
                'edit_flag' => true,
                'add_visible_flag' => true,
                'important_flag' => false,
                'bulk_edit_allowed' => true,
                'searchable_flag' => true,
                'filtering_allowed' => true,
                'sortable_flag' => true,
                'mandatory_flag' => false,
            ],
            'pipedrive_add_time' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'pipedrive_update_time' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Generate a valid 40-character custom field key
     */
    private function generateCustomFieldKey(): string
    {
        return str_pad(
            bin2hex(random_bytes(20)),
            40,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Create a custom field for a specific entity type
     */
    public function forEntity(string $entityType): static
    {
        return $this->state(function (array $attributes) use ($entityType) {
            return [
                'entity_type' => $entityType,
                'pipedrive_data' => array_merge($attributes['pipedrive_data'] ?? [], [
                    'entity_type' => $entityType,
                ]),
            ];
        });
    }

    /**
     * Create a custom field with a specific key
     */
    public function withKey(string $key): static
    {
        return $this->state(function (array $attributes) use ($key) {
            return [
                'key' => $key,
                'pipedrive_data' => array_merge($attributes['pipedrive_data'] ?? [], [
                    'key' => $key,
                ]),
            ];
        });
    }

    /**
     * Create an inactive custom field
     */
    public function inactive(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'active_flag' => false,
                'pipedrive_data' => array_merge($attributes['pipedrive_data'] ?? [], [
                    'active_flag' => false,
                ]),
            ];
        });
    }

    /**
     * Create a mandatory custom field
     */
    public function mandatory(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'pipedrive_data' => array_merge($attributes['pipedrive_data'] ?? [], [
                    'mandatory_flag' => true,
                ]),
            ];
        });
    }

    /**
     * Create a custom field that is not editable (system field)
     */
    public function systemField(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'pipedrive_data' => array_merge($attributes['pipedrive_data'] ?? [], [
                    'edit_flag' => false,
                ]),
            ];
        });
    }

    /**
     * Create a custom field with specific field type
     */
    public function ofType(string $fieldType): static
    {
        return $this->state(function (array $attributes) use ($fieldType) {
            return [
                'field_type' => $fieldType,
                'pipedrive_data' => array_merge($attributes['pipedrive_data'] ?? [], [
                    'field_type' => $fieldType,
                ]),
            ];
        });
    }
}
