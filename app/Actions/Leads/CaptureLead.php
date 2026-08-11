<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Models\Listing;
use App\Models\ListingLead;

/**
 * Records an enquiry sent from a listing's public page.
 *
 * Leads are captured from anonymous visitors — there is deliberately no
 * user_id: requiring an account to contact a business would suppress most
 * genuine enquiries. The abuse surface that opens is handled at the route
 * (throttle) rather than by identity.
 */
final class CaptureLead
{
    /** @param array<string, mixed> $data */
    public function execute(Listing $listing, array $data): ListingLead
    {
        return ListingLead::create([
            'listing_id' => $listing->getKey(),
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'phone' => $data['phone'] ?? null,
            'message' => (string) $data['message'],
        ]);
    }
}
