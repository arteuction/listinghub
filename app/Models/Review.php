<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property ModerationStatus $status
 * @property Listing $listing reviews.listing_id is NOT NULL
 */
class Review extends Model
{
    use HasFactory;

    protected $fillable = ['listing_id', 'user_id', 'rating', 'body', 'status'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ModerationStatus::class,
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'mediable')->orderBy('sort_order');
    }
}
