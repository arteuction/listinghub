<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
| 3.4.2 — Member/Admin CRUD for listing products.
|
| Key invariants:
|   • A member may manage products only on a listing they own.
|   • A member's status is limited to draft/published — suspended is
|     staff-only (enforced by Member\ProductRequest vs Admin\ProductRequest).
|   • Slugs are unique per listing, not globally.
|   • An admin (permission:manage settings, the current blanket admin gate)
|     may manage products on ANY listing, including setting Suspended.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function productOwner(array $attrs = []): User
{
    $user = User::factory()->create(array_merge(['email_verified_at' => now()], $attrs));
    $user->assignRole('member');

    return $user;
}

function productAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

// ------------------------------------------------------------------ member CRUD

it('lets an owner create a product for their listing', function () {
    $user = productOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('member.listings.products.store', $listing), [
        'name' => 'Standard package',
        'price_minor' => 1999,
        'currency' => 'USD',
    ])->assertRedirect(route('member.listings.products.index', $listing));

    $product = Product::query()->where('listing_id', $listing->id)->firstOrFail();
    expect($product->name)->toBe('Standard package')
        ->and($product->price_minor)->toBe(1999)
        ->and($product->status->value)->toBe('draft')
        ->and($product->slug)->not->toBeEmpty();
});

it('blocks a member from managing a product on a listing they do not own', function () {
    $owner = productOwner();
    $stranger = productOwner();
    $listing = Listing::factory()->create(['user_id' => $owner->id]);
    $product = Product::factory()->for($listing)->create();

    $this->actingAs($stranger)->get(route('member.listings.products.index', $listing))->assertForbidden();
    $this->actingAs($stranger)->get(route('member.listings.products.create', $listing))->assertForbidden();
    $this->actingAs($stranger)->post(route('member.listings.products.store', $listing), [
        'name' => 'x', 'price_minor' => 100,
    ])->assertForbidden();
    $this->actingAs($stranger)->get(route('member.listings.products.edit', [$listing, $product]))->assertForbidden();
    $this->actingAs($stranger)->put(route('member.listings.products.update', [$listing, $product]), [
        'name' => 'x', 'price_minor' => 100,
    ])->assertForbidden();
    $this->actingAs($stranger)->delete(route('member.listings.products.destroy', [$listing, $product]))->assertForbidden();
});

it('rejects a product id that belongs to a different listing', function () {
    $user = productOwner();
    $listingA = Listing::factory()->create(['user_id' => $user->id]);
    $listingB = Listing::factory()->create(['user_id' => $user->id]);
    $productB = Product::factory()->for($listingB)->create();

    $this->actingAs($user)->get(route('member.listings.products.edit', [$listingA, $productB]))->assertNotFound();
});

it('rejects suspended as a member-chosen status', function () {
    $user = productOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->for($listing)->create();

    $this->actingAs($user)->put(route('member.listings.products.update', [$listing, $product]), [
        'name' => $product->name,
        'price_minor' => $product->price_minor,
        'status' => 'suspended',
    ])->assertSessionHasErrors('status');
});

it('lets the owner toggle between draft and published', function () {
    $user = productOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->for($listing)->create(['status' => 'draft']);

    $this->actingAs($user)->put(route('member.listings.products.update', [$listing, $product]), [
        'name' => $product->name,
        'price_minor' => $product->price_minor,
        'status' => 'published',
    ])->assertRedirect();

    expect($product->fresh()->status->value)->toBe('published');
});

it('scopes product slugs per listing, not globally', function () {
    $user = productOwner();
    $listingA = Listing::factory()->create(['user_id' => $user->id]);
    $listingB = Listing::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('member.listings.products.store', $listingA), [
        'name' => 'Standard package', 'price_minor' => 100,
    ]);
    $this->actingAs($user)->post(route('member.listings.products.store', $listingB), [
        'name' => 'Standard package', 'price_minor' => 100,
    ]);

    $slugA = Product::query()->where('listing_id', $listingA->id)->value('slug');
    $slugB = Product::query()->where('listing_id', $listingB->id)->value('slug');

    expect($slugA)->toBe('standard-package')
        ->and($slugB)->toBe('standard-package'); // same slug allowed in different listings
});

it('deletes a product the owner controls', function () {
    $user = productOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->for($listing)->create();

    $this->actingAs($user)->delete(route('member.listings.products.destroy', [$listing, $product]))
        ->assertRedirect(route('member.listings.products.index', $listing));

    expect(Product::query()->find($product->id))->toBeNull();
});

// ------------------------------------------------------------------ admin CRUD

it('lets an admin manage products on any listing, including suspending', function () {
    $admin = productAdmin();
    $owner = productOwner();
    $listing = Listing::factory()->create(['user_id' => $owner->id]);
    $product = Product::factory()->for($listing)->create(['status' => 'published']);

    $this->actingAs($admin)->put(route('admin.listings.products.update', [$listing, $product]), [
        'name' => $product->name,
        'price_minor' => $product->price_minor,
        'status' => 'suspended',
    ])->assertRedirect(route('admin.listings.products.index', $listing));

    expect($product->fresh()->status->value)->toBe('suspended');
});

it('blocks a plain member from the admin product routes', function () {
    $user = productOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('admin.listings.products.index', $listing))->assertForbidden();
});
