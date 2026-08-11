<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\ModerationStatus;
use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use App\Services\Settings\SiteSettings;
use Illuminate\Support\Facades\DB;

/**
 * Records a member's review of a listing.
 *
 * Honours `listinghub.moderation.reviews_require_approval`: when on (the
 * default) the review lands as Pending and is invisible until a moderator
 * approves it; when off it is Approved immediately and the listing's rating
 * aggregate is recomputed in the same transaction.
 *
 * One review per user per listing is a UNIQUE constraint in the schema
 * (`[listing_id, user_id]`); the request layer checks it too so the user gets
 * a validation message rather than a 500.
 */
final class SubmitReview
{
    public function __construct(
        private readonly RecalculateListingRating $recalculate,
        private readonly SiteSettings $settings,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(Listing $listing, User $author, array $data): Review
    {
        $needsApproval = $this->settings->bool('reviews_require_approval');

        return DB::transaction(function () use ($listing, $author, $data, $needsApproval): Review {
            $review = Review::create([
                'listing_id' => $listing->getKey(),
                'user_id' => $author->getKey(),
                'rating' => (int) $data['rating'],
                'body' => $data['body'] ?? null,
                'status' => $needsApproval
                    ? ModerationStatus::Pending->value
                    : ModerationStatus::Approved->value,
            ]);

            // Only an approved review moves the public average.
            if (! $needsApproval) {
                $this->recalculate->handle($listing);
            }

            return $review;
        });
    }
}
