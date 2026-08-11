<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\ModerationStatus;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * Approves or rejects a review and rebuilds the listing's rating aggregate.
 *
 * The aggregate is recomputed on EVERY decision, including rejection and
 * re-approval of an already-decided review, because a moderator reversing an
 * earlier call must move the average back — incrementing on approve only would
 * leave a rejected review's stars in the public score forever.
 */
final class ModerateReview
{
    public function __construct(private readonly RecalculateListingRating $recalculate) {}

    public function handle(Review $review, ModerationStatus $decision): Review
    {
        return DB::transaction(function () use ($review, $decision): Review {
            $review->status = $decision;
            $review->save();

            $this->recalculate->handle($review->listing);

            return $review;
        });
    }
}
