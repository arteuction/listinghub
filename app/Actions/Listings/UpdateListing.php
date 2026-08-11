<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Models\Listing;

/**
 * Applies owner-editable fields.
 *
 * The slug is deliberately NOT regenerated when the title changes: the old URL
 * is already indexed and may be linked from elsewhere, and silently breaking it
 * costs more than an out-of-date slug. Status is untouched — editing content is
 * not a transition.
 */
final class UpdateListing
{
    /** @param array<string, mixed> $data */
    public function execute(Listing $listing, array $data): Listing
    {
        $listing->update([
            'category_id' => (int) $data['category_id'],
            'settlement_id' => $data['settlement_id'] ?? null,
            'title' => (string) $data['title'],
            'description' => $data['description'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        return $listing;
    }
}
