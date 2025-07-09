<?php

namespace Keggermont\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Builder;
use Keggermont\LaravelPipedrive\Data\PipedriveFileData;

class PipedriveFile extends BasePipedriveModel
{
    protected $table = 'pipedrive_files';

        protected $fillable = [
        'pipedrive_id',
        'name',
        'file_name',
        'file_type',
        'file_size',
        'url',
        'person_id',
        'org_id',
        'deal_id',
        'user_id',
        'active_flag',
        'pipedrive_data',
        'pipedrive_add_time',
        'pipedrive_update_time',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'inline_flag' => 'boolean',
            'file_size' => 'integer',
        ]);
    }

    public static function getPipedriveEntityName(): string
    {
        return 'files';
    }

    protected static function getDtoClass(): string
    {
        return PipedriveFileData::class;
    }

    // Scopes
    public function scopeForDeal(Builder $query, int $dealId): Builder
    {
        return $query->where('deal_id', $dealId);
    }

    public function scopeForPerson(Builder $query, int $personId): Builder
    {
        return $query->where('person_id', $personId);
    }

    public function scopeForOrganization(Builder $query, int $orgId): Builder
    {
        return $query->where('org_id', $orgId);
    }

    public function scopeForActivity(Builder $query, int $activityId): Builder
    {
        return $query->where('activity_id', $activityId);
    }

    public function scopeByType(Builder $query, string $fileType): Builder
    {
        return $query->where('file_type', $fileType);
    }

    public function scopeInline(Builder $query): Builder
    {
        return $query->where('inline_flag', true);
    }

    // Helper methods
    public function getFormattedFileSize(): string
    {
        if (!$this->file_size) {
            return 'Unknown size';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function isImage(): bool
    {
        return in_array(strtolower($this->file_type ?? ''), [
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'
        ]);
    }

    public function isDocument(): bool
    {
        return in_array(strtolower($this->file_type ?? ''), [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'
        ]);
    }

    // Relations
    public function user()
    {
        return $this->belongsTo(PipedriveUser::class, 'user_id', 'pipedrive_id');
    }

    public function person()
    {
        return $this->belongsTo(PipedrivePerson::class, 'person_id', 'pipedrive_id');
    }

    public function organization()
    {
        return $this->belongsTo(PipedriveOrganization::class, 'org_id', 'pipedrive_id');
    }

    public function deal()
    {
        return $this->belongsTo(PipedriveDeal::class, 'deal_id', 'pipedrive_id');
    }
}