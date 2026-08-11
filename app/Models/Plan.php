<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property BillingInterval $interval
 * @property int $price_minor
 * @property bool $is_featured
 * @property bool $is_active
 */
class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'name', 'slug', 'description', 'price_minor', 'currency',
        'interval', 'listing_limit', 'gallery_limit', 'is_featured', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'interval' => BillingInterval::class,
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isUnlimited(): bool
    {
        return is_null($this->listing_limit);
    }
}
