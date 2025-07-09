<?php

namespace Keggermont\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class PipedriveCustomField extends Model
{
    protected $table = 'pipedrive_custom_fields';

    protected $fillable = [
        'pipedrive_id',
        'name',
        'key',
        'field_type',
        'entity_type',
        'active_flag',
        'pipedrive_data',
        'pipedrive_add_time',
        'pipedrive_update_time',
    ];

    protected $casts = [
        'active_flag' => 'boolean',
        'pipedrive_data' => 'array',
        'pipedrive_add_time' => 'datetime',
        'pipedrive_update_time' => 'datetime',
    ];

    /**
     * Entity types constants
     */
    public const ENTITY_DEAL = 'deal';
    public const ENTITY_PERSON = 'person';
    public const ENTITY_ORGANIZATION = 'organization';
    public const ENTITY_PRODUCT = 'product';
    public const ENTITY_ACTIVITY = 'activity';
    public const ENTITY_NOTE = 'note';

    /**
     * Field types constants
     */
    public const TYPE_VARCHAR = 'varchar';
    public const TYPE_VARCHAR_AUTO = 'varchar_auto';
    public const TYPE_TEXT = 'text';
    public const TYPE_DOUBLE = 'double';
    public const TYPE_MONETARY = 'monetary';
    public const TYPE_SET = 'set';
    public const TYPE_ENUM = 'enum';
    public const TYPE_USER = 'user';
    public const TYPE_ORG = 'org';
    public const TYPE_PEOPLE = 'people';
    public const TYPE_PHONE = 'phone';
    public const TYPE_TIME = 'time';
    public const TYPE_TIMERANGE = 'timerange';
    public const TYPE_DATE = 'date';
    public const TYPE_DATERANGE = 'daterange';
    public const TYPE_ADDRESS = 'address';

    /**
     * Get all available entity types
     */
    public static function getEntityTypes(): array
    {
        return [
            self::ENTITY_DEAL,
            self::ENTITY_PERSON,
            self::ENTITY_ORGANIZATION,
            self::ENTITY_PRODUCT,
            self::ENTITY_ACTIVITY,
            self::ENTITY_NOTE,
        ];
    }

    /**
     * Get all available field types
     */
    public static function getFieldTypes(): array
    {
        return [
            self::TYPE_VARCHAR,
            self::TYPE_VARCHAR_AUTO,
            self::TYPE_TEXT,
            self::TYPE_DOUBLE,
            self::TYPE_MONETARY,
            self::TYPE_SET,
            self::TYPE_ENUM,
            self::TYPE_USER,
            self::TYPE_ORG,
            self::TYPE_PEOPLE,
            self::TYPE_PHONE,
            self::TYPE_TIME,
            self::TYPE_TIMERANGE,
            self::TYPE_DATE,
            self::TYPE_DATERANGE,
            self::TYPE_ADDRESS,
        ];
    }

    /**
     * Scope to filter by entity type
     */
    public function scopeForEntity(Builder $query, string $entityType): Builder
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope to filter by field type
     */
    public function scopeOfType(Builder $query, string $fieldType): Builder
    {
        return $query->where('field_type', $fieldType);
    }

    /**
     * Scope to get only active fields
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active_flag', true);
    }

    /**
     * Scope to get only custom fields (not default Pipedrive fields)
     */
    public function scopeCustomOnly(Builder $query): Builder
    {
        return $query->where('edit_flag', true);
    }



    /**
     * Scope to get only mandatory fields
     */
    public function scopeMandatory(Builder $query): Builder
    {
        return $query->where('mandatory_flag', true);
    }

    /**
     * Scope to get fields visible in add dialog
     */
    public function scopeVisibleInAdd(Builder $query): Builder
    {
        return $query->where('add_visible_flag', true);
    }

    /**
     * Scope to get fields visible in detail view
     */
    public function scopeVisibleInDetails(Builder $query): Builder
    {
        return $query->where('details_visible_flag', true);
    }

    /**
     * Check if this field is a custom field
     */
    public function isCustomField(): bool
    {
        return $this->edit_flag;
    }



    /**
     * Check if this field is mandatory
     */
    public function isMandatory(): bool
    {
        return $this->mandatory_flag;
    }

    /**
     * Check if this field has options (set or enum type)
     */
    public function hasOptions(): bool
    {
        return in_array($this->field_type, [self::TYPE_SET, self::TYPE_ENUM]) && !empty($this->options);
    }

    /**
     * Get field options for set/enum fields
     */
    public function getOptions(): array
    {
        return $this->hasOptions() ? $this->options : [];
    }

    /**
     * Check if field is a relation type (user, org, people)
     */
    public function isRelationType(): bool
    {
        return in_array($this->field_type, [self::TYPE_USER, self::TYPE_ORG, self::TYPE_PEOPLE]);
    }

    /**
     * Get human-readable field type description
     */
    public function getFieldTypeDescription(): string
    {
        return match ($this->field_type) {
            self::TYPE_VARCHAR => 'Text (up to 255 characters)',
            self::TYPE_VARCHAR_AUTO => 'Autocomplete Text',
            self::TYPE_TEXT => 'Large Text',
            self::TYPE_DOUBLE => 'Numerical',
            self::TYPE_MONETARY => 'Monetary',
            self::TYPE_SET => 'Multiple Options',
            self::TYPE_ENUM => 'Single Option',
            self::TYPE_USER => 'User',
            self::TYPE_ORG => 'Organization',
            self::TYPE_PEOPLE => 'Person',
            self::TYPE_PHONE => 'Phone',
            self::TYPE_TIME => 'Time',
            self::TYPE_TIMERANGE => 'Time Range',
            self::TYPE_DATE => 'Date',
            self::TYPE_DATERANGE => 'Date Range',
            self::TYPE_ADDRESS => 'Address',
            default => 'Unknown',
        };
    }

    /**
     * Create or update a custom field from Pipedrive API data
     */
    public static function createOrUpdateFromPipedriveData(array $data, string $entityType): self
    {
        // Skip fields without an ID (system/primary fields)
        if (!isset($data['id']) || $data['id'] === null) {
            throw new \InvalidArgumentException("Field data must have an 'id' field. Field key: " . ($data['key'] ?? 'unknown'));
        }

        // Helper function to safely convert values
        $safeValue = function($value, $default = null) {
            if (is_array($value) || is_object($value)) {
                return json_encode($value);
            }
            return $value ?? $default;
        };

        // Helper function to safely parse timestamps
        $safeTimestamp = function($value) {
            if (empty($value)) return null;
            if (is_array($value) || is_object($value)) return null;

            try {
                $parsed = Carbon::parse($value);

                // Reject invalid dates like 1970-01-01 00:00:00 (Unix epoch)
                if ($parsed->year <= 1970) {
                    return null;
                }

                return $parsed;
            } catch (\Exception $e) {
                return null;
            }
        };

        return self::updateOrCreate(
            [
                'pipedrive_field_id' => $data['id'],
                'entity_type' => $entityType,
            ],
            [
                'field_key' => $safeValue($data['key']),
                'name' => $safeValue($data['name']),
                'field_type' => $safeValue($data['field_type']),
                'order_nr' => is_numeric($data['order_nr'] ?? null) ? $data['order_nr'] : null,
                'options' => is_array($data['options'] ?? null) ? $data['options'] : null,
                'mandatory_flag' => (bool) ($data['mandatory_flag'] ?? false),
                'active_flag' => (bool) ($data['active_flag'] ?? true),
                'edit_flag' => (bool) ($data['edit_flag'] ?? true),
                'add_visible_flag' => (bool) ($data['add_visible_flag'] ?? true),
                'details_visible_flag' => (bool) ($data['details_visible_flag'] ?? true),
                'index_visible_flag' => (bool) ($data['index_visible_flag'] ?? true),
                'important_flag' => (bool) ($data['important_flag'] ?? false),
                'bulk_edit_allowed' => (bool) ($data['bulk_edit_allowed'] ?? true),
                'filtering_allowed' => (bool) ($data['filtering_allowed'] ?? true),
                'sortable_flag' => (bool) ($data['sortable_flag'] ?? true),
                'searchable_flag' => (bool) ($data['searchable_flag'] ?? false),
                'json_column_flag' => (bool) ($data['json_column_flag'] ?? false),
                'last_updated_by_user_id' => is_numeric($data['last_updated_by_user_id'] ?? null) ? $data['last_updated_by_user_id'] : null,
                'pipedrive_add_time' => $safeTimestamp($data['add_time'] ?? null),
                'pipedrive_update_time' => $safeTimestamp($data['update_time'] ?? null),
            ]
        );
    }
}
