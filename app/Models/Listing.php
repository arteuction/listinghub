<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ListingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Declares the attributes whose runtime type comes from casts() — without
 * these, static analysis reads the raw column types (string) and rejects
 * assigning the enum or the Carbon instance.
 *
 * @property ListingStatus $status
 * @property Carbon|null $published_at
 * @property Category $category listings.category_id is NOT NULL
 */
class Listing extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'category_id', 'plan_id', 'settlement_id', 'organization_id',
        'title', 'slug', 'description', 'status', 'is_featured',
        'phone', 'email', 'website', 'address', 'latitude', 'longitude',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'is_featured' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'rating_avg' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Only publicly visible listings. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ListingStatus::Published->value);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'mediable')->orderBy('sort_order');
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(ListingHour::class);
    }

    public function hourExceptions(): HasMany
    {
        return $this->hasMany(ListingHourException::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(ListingLead::class);
    }

    /** Members who saved this listing. */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    public function claims(): HasMany
    {
        return $this->hasMany(ListingClaim::class);
    }
}
