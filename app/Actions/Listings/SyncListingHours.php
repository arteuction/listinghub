<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Models\Listing;
use App\Models\ListingHour;
use Illuminate\Support\Facades\DB;

/**
 * Full-replacement sync of a listing's weekly hours (0=Sunday..6=Saturday,
 * matching PHP's own `date('w')`). The payload is the complete desired
 * state — a day absent from it is treated as closed, not "unchanged" —
 * because a partial PATCH-style update would let a stale "open" row survive
 * silently after the owner removed that day from the form.
 *
 * The table is at most 7 rows, so delete-then-insert inside one transaction
 * is simpler and just as safe as a diffed upsert.
 */
final class SyncListingHours
{
    /**
     * @param  list<array<string, mixed>>  $days  Each: day_of_week, opens_at, closes_at, is_closed.
     */
    public function handle(Listing $listing, array $days): void
    {
        DB::transaction(function () use ($listing, $days): void {
            ListingHour::query()->where('listing_id', $listing->getKey())->delete();

            foreach ($days as $day) {
                $isClosed = (bool) ($day['is_closed'] ?? false);

                ListingHour::create([
                    'listing_id' => $listing->getKey(),
                    'day_of_week' => (int) $day['day_of_week'],
                    'opens_at' => $isClosed ? null : ($day['opens_at'] ?? null),
                    'closes_at' => $isClosed ? null : ($day['closes_at'] ?? null),
                    'is_closed' => $isClosed,
                ]);
            }
        });
    }
}
