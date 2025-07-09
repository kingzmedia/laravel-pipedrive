<?php

namespace Keggermont\LaravelPipedrive\Models;

use Illuminate\Database\Eloquent\Builder;
use Keggermont\LaravelPipedrive\Data\PipedriveProductData;

class PipedriveProduct extends BasePipedriveModel
{
    protected $table = 'pipedrive_products';

        protected $fillable = [
        'pipedrive_id',
        'name',
        'code',
        'unit',
        'tax',
        'owner_id',
        'active_flag',
        'pipedrive_data',
        'pipedrive_add_time',
        'pipedrive_update_time',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'prices' => 'array',
            'tax' => 'decimal:2',
            'deals_count' => 'integer',
            'files_count' => 'integer',
            'followers_count' => 'integer',
            'first_char' => 'integer',
        ]);
    }

    public static function getPipedriveEntityName(): string
    {
        return 'products';
    }

    protected static function getDtoClass(): string
    {
        return PipedriveProductData::class;
    }

    // Scopes
    public function scopeForOwner(Builder $query, int $ownerId): Builder
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function scopeWithDeals(Builder $query): Builder
    {
        return $query->where('deals_count', '>', 0);
    }

    // Helper methods
    public function hasDeals(): bool
    {
        return $this->deals_count > 0;
    }

    public function hasFiles(): bool
    {
        return $this->files_count > 0;
    }

    public function hasFollowers(): bool
    {
        return $this->followers_count > 0;
    }

    public function getFormattedTax(): ?string
    {
        return $this->tax ? number_format($this->tax, 2) . '%' : null;
    }

    public function getPriceForCurrency(string $currency): ?array
    {
        if (!$this->prices || !is_array($this->prices)) {
            return null;
        }

        foreach ($this->prices as $price) {
            if (isset($price['currency']) && $price['currency'] === $currency) {
                return $price;
            }
        }

        return null;
    }

    public function getDefaultPrice(): ?array
    {
        if (!$this->prices || !is_array($this->prices)) {
            return null;
        }

        return $this->prices[0] ?? null;
    }

    // Relations
    public function owner()
    {
        return $this->belongsTo(PipedriveUser::class, 'owner_id', 'pipedrive_id');
    }
}