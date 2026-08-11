<?php

declare(strict_types=1);

namespace App\Actions\Claims;

use App\Enums\ModerationStatus;
use App\Models\Listing;
use App\Models\ListingClaim;
use App\Models\User;
use App\Support\StoredClaimDocument;

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
    public function execute(Listing $listing, User $claimant, array $data, ?StoredClaimDocument $document = null): ListingClaim
    {
        return ListingClaim::create([
            'listing_id' => $listing->getKey(),
            'user_id' => $claimant->getKey(),
            'status' => ModerationStatus::Pending->value,
            'message' => $data['message'] ?? null,
            'document_path' => $document?->path,
            'document_disk' => $document?->disk,
            'document_mime' => $document?->mime,
            'document_size' => $document?->sizeBytes,
            'document_sha256' => $document?->sha256,
            'document_original_name' => $document?->originalName,
        ]);
    }
}
