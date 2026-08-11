<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Models\Listing;
use App\Models\ListingHourException;

/**
 * A date-specific override of the weekly schedule — a holiday closure, a
 * one-off early close, etc. `is_closed` defaults true: the overwhelming
 * majority of exceptions are "closed today", and requiring the owner to
 * explicitly opt into a still-open exception (by supplying both times) is
 * the safer default.
 */
final class CreateHourException
{
    /** @param  array<string, mixed>  $data */
    public function execute(Listing $listing, array $data): ListingHourException
    {
        $isClosed = (bool) ($data['is_closed'] ?? true);

        return ListingHourException::create([
            'listing_id' => $listing->getKey(),
            'date' => $data['date'],
            'opens_at' => $isClosed ? null : ($data['opens_at'] ?? null),
            'closes_at' => $isClosed ? null : ($data['closes_at'] ?? null),
            'is_closed' => $isClosed,
            'note' => $data['note'] ?? null,
        ]);
    }
}
