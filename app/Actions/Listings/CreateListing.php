<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates a member-owned listing as a Draft.
 *
 * Always Draft, never straight to Published: going public is a status
 * transition, and every transition goes through TransitionListing so the
 * closed transition map stays the single source of truth.
 */
final class CreateListing
{
    /** @param array<string, mixed> $data */
    public function execute(User $owner, array $data): Listing
    {
        return Listing::create([
            'user_id' => $owner->getKey(),
            'category_id' => (int) $data['category_id'],
            'settlement_id' => $data['settlement_id'] ?? null,
            'title' => (string) $data['title'],
            'slug' => $this->uniqueSlug((string) $data['title']),
            'description' => $data['description'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => ListingStatus::Draft->value,
        ]);
    }

    /**
     * Prefers the clean slug and only falls back to a suffix when it is taken,
     * so ordinary listings keep readable URLs. `slug` is unique in the schema,
     * which is what ultimately guarantees correctness here.
     */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'obiava';
        }

        if (! Listing::withTrashed()->where('slug', $base)->exists()) {
            return $base;
        }

        do {
            $candidate = $base.'-'.Str::lower(Str::random(8));
        } while (Listing::withTrashed()->where('slug', $candidate)->exists());

        return $candidate;
    }
}
