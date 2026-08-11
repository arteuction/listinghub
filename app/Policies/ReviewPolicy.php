<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

/**
 * A review belongs to its author, not to the reviewed listing's owner — a
 * business must never be able to edit or delete criticism of itself. Only the
 * author and holders of `moderate reviews` may act on one.
 */
class ReviewPolicy
{
    public function create(User $user): bool
    {
        // Same reasoning as ListingPolicy::create — an unverified address makes
        // the rating system trivially sock-puppetable.
        return $user->hasVerifiedEmail();
    }

    public function update(User $user, Review $review): bool
    {
        return $this->owns($user, $review);
    }

    public function delete(User $user, Review $review): bool
    {
        return $this->owns($user, $review) || $user->can('moderate reviews');
    }

    public function moderate(User $user): bool
    {
        return $user->can('moderate reviews');
    }

    private function owns(User $user, Review $review): bool
    {
        return (int) $review->user_id === (int) $user->getKey();
    }
}
