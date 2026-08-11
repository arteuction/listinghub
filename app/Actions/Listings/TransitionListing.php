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
    public function handle(Listing $listing, ListingTransition $transition): Listing
    {
        return DB::transaction(function () use ($listing, $transition): Listing {
            /** @var Listing $locked */
            $locked = Listing::query()
                ->lockForUpdate()
                ->findOrFail($listing->getKey());

            if ($locked->status !== $transition->fromStatus()) {
                throw InvalidListingTransition::for($transition, $locked->status);
            }

            $locked->status = $transition->toStatus();

            if ($transition->toStatus() === ListingStatus::Published && $locked->published_at === null) {
                $locked->published_at = now();
            }

            $locked->save();

            return $locked;
        });
    }
}
