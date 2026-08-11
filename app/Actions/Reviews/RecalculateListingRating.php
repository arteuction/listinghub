<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\ModerationStatus;
use App\Models\Listing;
use App\Models\Review;

/**
 * Recomputes listings.rating_avg / rating_count from the APPROVED reviews only.
 *
 * Derived, never incremented: a running counter drifts the moment a review is
 * rejected, re-approved or deleted, and there is no way to detect that it has.
 * Recomputing from the rows is O(reviews-per-listing), which at directory scale
 * is trivial, and is always correct regardless of how the set changed.
 */
final class RecalculateListingRating
{
    public function handle(Listing $listing): void
    {
        $approved = Review::query()
            ->where('listing_id', $listing->getKey())
            ->where('status', ModerationStatus::Approved->value);

        $count = (int) $approved->count();
        $avg = $count > 0 ? (float) $approved->avg('rating') : 0.0;

        $listing->forceFill([
            'rating_count' => $count,
            'rating_avg' => round($avg, 2),
        ])->save();
    }
}
