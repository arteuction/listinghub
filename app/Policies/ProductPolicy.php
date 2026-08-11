<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * A product is managed exactly like the listing it belongs to: its owner, or
 * a holder of `manage products`. There is no independent "publish" state
 * machine here — Draft/Published/Suspended is a plain field the owner
 * chooses freely between Draft and Published; only staff can set Suspended
 * (enforced in the request, not here, since it is a value constraint rather
 * than an authorisation one).
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // query-scoped by listing ownership, not policy-gated
    }

    public function view(User $user, Product $product): bool
    {
        return $this->owns($user, $product) || $user->can('manage products');
    }

    public function create(User $user, ?Product $product = null): bool
    {
        return true; // gated per-listing in the controller via ListingPolicy::update
    }

    public function update(User $user, Product $product): bool
    {
        return $this->owns($user, $product) || $user->can('manage products');
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->owns($user, $product) || $user->can('manage products');
    }

    private function owns(User $user, Product $product): bool
    {
        return (int) $product->listing->user_id === (int) $user->getKey();
    }
}
