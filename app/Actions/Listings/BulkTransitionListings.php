<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Enums\ListingTransition;
use App\Exceptions\InvalidListingTransition;
use App\Models\Listing;

/**
 * Applies one legal transition to many listings, reusing TransitionListing so
 * every item gets the same guard + row lock + per-item transaction. Failures
 * are isolated: an illegal transition (or a missing id) is recorded and the
 * batch continues — one bad row never rolls back the others.
 */
class BulkTransitionListings
{
    public function __construct(private readonly TransitionListing $transition) {}

    /**
     * @param  iterable<int|string>  $ids
     * @return array{transitioned: list<int>, failed: list<array{id:int, reason:string}>}
     */
    public function handle(iterable $ids, ListingTransition $transition): array
    {
        $transitioned = [];
        $failed = [];
        $seen = [];

        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0 || isset($seen[$id])) {
                continue; // skip invalid/duplicate ids
            }
            $seen[$id] = true;

            $listing = Listing::query()->find($id);

            if ($listing === null) {
                $failed[] = ['id' => $id, 'reason' => 'Listing not found.'];

                continue;
            }

            try {
                $this->transition->handle($listing, $transition);
                $transitioned[] = $id;
            } catch (InvalidListingTransition $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        return ['transitioned' => $transitioned, 'failed' => $failed];
    }
}
