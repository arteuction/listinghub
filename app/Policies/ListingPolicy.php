<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;

/**
 * Owner-scoped authorisation for listings.
 *
 * Members hold no admin permissions (see RoleSeeder); their reach is defined
 * entirely here, by ownership. Staff and admins pass through the `manage
 * listings` / `moderate listings` permissions instead of by owning the row.
 */
class ListingPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // everyone may list their own; the query does the scoping
    }

    /**
     * A non-published listing is visible only to its owner and to moderators.
     * The public detail page does not consult this policy — it 404s instead, so
     * a draft stays indistinguishable from a listing that does not exist.
     */
    public function view(User $user, Listing $listing): bool
    {
        return $listing->status === ListingStatus::Published
            || $this->owns($user, $listing)
            || $user->can('moderate listings');
    }

    /**
     * Creation requires a confirmed address. Without this the verification step
     * would be decorative: anyone could publish from an address they cannot
     * receive mail at, and leads would go nowhere.
     */
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function update(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing) || $user->can('manage listings');
    }

    public function delete(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing) || $user->can('manage listings');
    }

    /**
     * Submitting for review is an owner-only act — a moderator approving is a
     * different transition, guarded by `moderate listings`.
     */
    public function submit(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing) && $listing->status === ListingStatus::Draft;
    }

    private function owns(User $user, Listing $listing): bool
    {
        return (int) $listing->user_id === (int) $user->getKey();
    }
}
