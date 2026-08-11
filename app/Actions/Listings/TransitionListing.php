<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Enums\ListingStatus;
use App\Enums\ListingTransition;
use App\Exceptions\InvalidListingTransition;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

/**
 * Applies a single legal status transition to a listing.
 *
 * The listing is re-read under a row lock INSIDE the transaction and the guard
 * runs against that locked, authoritative status — so two concurrent
 * transitions serialize and a stale in-memory instance can never bypass the
 * map. Stamps published_at on the first publish only; suspend/disapprove never
 * clear it. No status logic lives on the model.
 */
class TransitionListing
{
    public function handle(Listing $listing, ListingTransition $transition, ?string $note = null): Listing
    {
        return DB::transaction(function () use ($listing, $transition, $note): Listing {
            /** @var Listing $locked */
            $locked = Listing::query()
                ->lockForUpdate()
                ->findOrFail($listing->getKey());

            if ($locked->status !== $transition->fromStatus()) {
                throw InvalidListingTransition::for($transition, $locked->status);
            }

            $locked->status = $transition->toStatus();

            // The note travels WITH the move that requires one, and any move
            // leaving Draft clears it: a resubmitted listing is a new review,
            // and a stale "fix X" banner on a published page helps no one.
            if ($transition->requiresReason()) {
                $locked->moderation_note = (string) $note;
            } elseif ($transition->fromStatus() === ListingStatus::Draft) {
                $locked->moderation_note = null;
            }

            if ($transition->toStatus() === ListingStatus::Published && $locked->published_at === null) {
                $locked->published_at = now();
            }

            $locked->save();

            return $locked;
        });
    }
}
