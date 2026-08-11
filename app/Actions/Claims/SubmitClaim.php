<?php

declare(strict_types=1);

namespace App\Actions\Claims;

use App\Enums\ModerationStatus;
use App\Models\Listing;
use App\Models\ListingClaim;
use App\Models\User;

/**
 * Records a member's claim of ownership over an existing listing.
 *
 * A claim NEVER transfers ownership by itself — it only opens a moderation
 * item. Transfer happens in ApproveClaim, under staff review, because the
 * whole point of the claim flow is that the requester cannot yet prove they
 * own the row.
 */
final class SubmitClaim
{
    /** @param array<string, mixed> $data */
    public function execute(Listing $listing, User $claimant, array $data): ListingClaim
    {
        return ListingClaim::create([
            'listing_id' => $listing->getKey(),
            'user_id' => $claimant->getKey(),
            'status' => ModerationStatus::Pending->value,
            'message' => $data['message'] ?? null,
            'document_path' => $data['document_path'] ?? null,
        ]);
    }
}
