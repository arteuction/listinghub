<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @property ProductStatus $status */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id', 'organization_id', 'name', 'slug', 'description',
        'price_minor', 'currency', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'status' => ProductStatus::class,
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'mediable')->orderBy('sort_order');
    }

    /** @return HasMany<AttributeValue, $this> */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order')->orderBy('id');
    }
}
