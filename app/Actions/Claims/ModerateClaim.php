<?php

declare(strict_types=1);

namespace App\Actions\Claims;

use App\Enums\ModerationStatus;
use App\Models\ListingClaim;
use Illuminate\Support\Facades\DB;

/**
 * Decides a claim. Approving TRANSFERS the listing to the claimant and
 * rejects every other pending claim on the same listing in the same
 * transaction — leaving them open would invite a second approval that
 * silently re-transfers a listing already handed over.
 *
 * Rejection changes nothing but the claim's own status.
 */
final class ModerateClaim
{
    public function handle(ListingClaim $claim, ModerationStatus $decision): ListingClaim
    {
        return DB::transaction(function () use ($claim, $decision): ListingClaim {
            $claim->status = $decision;
            $claim->save();

            if ($decision !== ModerationStatus::Approved) {
                return $claim;
            }

            // Lock the listing so two moderators cannot approve competing
            // claims concurrently and race the ownership write.
            $listing = $claim->listing()->lockForUpdate()->firstOrFail();
            $listing->user_id = $claim->user_id;
            $listing->save();

            ListingClaim::query()
                ->where('listing_id', $claim->listing_id)
                ->whereKeyNot($claim->getKey())
                ->where('status', ModerationStatus::Pending->value)
                ->update(['status' => ModerationStatus::Rejected->value]);

            return $claim;
        });
    }
}
