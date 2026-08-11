<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Enums\ModerationStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingClaim;
use App\Models\ListingLead;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SiteSettings;
use Database\Seeders\RoleSeeder;

/*
| Admin panel (3.5.1): listing status transitions, category CRUD, leads,
| settings, dashboard.
|
| The weight is on what an operator must NOT be able to do by accident:
| an illegal status move, a category placed under itself, deleting a category
| that still holds listings, and a settings switch that reports a state the
| application does not honour.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function panelAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function panelMember(): User
{
    $user = User::factory()->create();
    $user->assignRole('member');

    return $user;
}

// ─── access ─────────────────────────────────────────────────────────────────

it('keeps a member out of every admin screen', function (string $route) {
    $this->actingAs(panelMember())->get($route)->assertForbidden();
})->with([
    '/admin',
    '/admin/listings',
    '/admin/categories',
    '/admin/leads',
    '/admin/settings',
    '/admin/users',
]);

it('keeps a guest out of the admin panel', function () {
    $this->get('/admin')->assertRedirect('/login');
});

// ─── listing status transitions ─────────────────────────────────────────────

it('approves a pending listing into published', function () {
    $listing = Listing::factory()->create(['status' => ListingStatus::Pending->value]);

    $this->actingAs(panelAdmin())
        ->post("/admin/listings/{$listing->slug}/transition", ['transition' => 'approve'])
        ->assertRedirect();

    expect($listing->fresh()->status)->toBe(ListingStatus::Published)
        ->and($listing->fresh()->published_at)->not->toBeNull();
});

it('suspends a published listing and restores it', function () {
    $listing = Listing::factory()->published()->create();

    $this->actingAs(panelAdmin())
        ->post("/admin/listings/{$listing->slug}/transition", ['transition' => 'suspend']);
    expect($listing->fresh()->status)->toBe(ListingStatus::Suspended);

    $this->actingAs(panelAdmin())
        ->post("/admin/listings/{$listing->slug}/transition", ['transition' => 'restore']);
    expect($listing->fresh()->status)->toBe(ListingStatus::Published);
});

it('refuses a transition that is not legal from the current status', function () {
    // approve leaves Pending; this listing is Published.
    $listing = Listing::factory()->published()->create();

    $this->actingAs(panelAdmin())
        ->from('/admin/listings')
        ->post("/admin/listings/{$listing->slug}/transition", ['transition' => 'approve'])
        ->assertRedirect('/admin/listings')
        ->assertSessionHasErrors('transition');

    expect($listing->fresh()->status)->toBe(ListingStatus::Published);
});

it('refuses an unknown transition name', function () {
    $listing = Listing::factory()->published()->create();

    $this->actingAs(panelAdmin())
        ->from('/admin/listings')
        ->post("/admin/listings/{$listing->slug}/transition", ['transition' => 'delete-everything'])
        ->assertSessionHasErrors('transition');

    expect($listing->fresh()->status)->toBe(ListingStatus::Published);
});

it('does not offer publishing a draft directly — that is the owner submit path', function () {
    $listing = Listing::factory()->create(['status' => ListingStatus::Draft->value]);

    $this->actingAs(panelAdmin())
        ->from('/admin/listings')
        ->post("/admin/listings/{$listing->slug}/transition", ['transition' => 'auto_publish'])
        ->assertSessionHasErrors('transition');

    expect($listing->fresh()->status)->toBe(ListingStatus::Draft);
});

it('filters the listing index by status', function () {
    $published = Listing::factory()->published()->create(['title' => 'Публикувана обява']);
    $draft = Listing::factory()->create(['title' => 'Чернова обява', 'status' => ListingStatus::Draft->value]);

    $this->actingAs(panelAdmin())
        ->get('/admin/listings?status=draft')
        ->assertOk()
        ->assertSee($draft->title)
        ->assertDontSee($published->title);
});

// ─── categories ─────────────────────────────────────────────────────────────

it('creates a category', function () {
    $this->actingAs(panelAdmin())->post('/admin/categories', [
        'name' => 'Ресторанти',
        'slug' => 'restoranti',
        'sort_order' => 5,
        'is_active' => '1',
    ])->assertRedirect('/admin/categories');

    expect(Category::query()->where('slug', 'restoranti')->exists())->toBeTrue();
});

it('rejects a slug that is not url-safe', function () {
    $this->actingAs(panelAdmin())
        ->from('/admin/categories/create')
        ->post('/admin/categories', ['name' => 'X', 'slug' => 'Не Слъг', 'sort_order' => 0])
        ->assertSessionHasErrors('slug');
});

it('rejects a duplicate slug but lets a category keep its own', function () {
    Category::factory()->create(['slug' => 'taken']);
    $mine = Category::factory()->create(['slug' => 'mine']);

    $this->actingAs(panelAdmin())
        ->from("/admin/categories/{$mine->id}/edit")
        ->put("/admin/categories/{$mine->id}", ['name' => 'X', 'slug' => 'taken', 'sort_order' => 0])
        ->assertSessionHasErrors('slug');

    $this->actingAs(panelAdmin())
        ->put("/admin/categories/{$mine->id}", ['name' => 'Ново име', 'slug' => 'mine', 'sort_order' => 0])
        ->assertRedirect('/admin/categories');

    expect($mine->fresh()->name)->toBe('Ново име');
});

it('refuses to place a category under itself', function () {
    $category = Category::factory()->create();

    $this->actingAs(panelAdmin())
        ->from("/admin/categories/{$category->id}/edit")
        ->put("/admin/categories/{$category->id}", [
            'name' => $category->name,
            'slug' => $category->slug,
            'parent_id' => $category->id,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('parent_id');

    expect($category->fresh()->parent_id)->toBeNull();
});

it('refuses to place a category under its own descendant', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs(panelAdmin())
        ->from("/admin/categories/{$parent->id}/edit")
        ->put("/admin/categories/{$parent->id}", [
            'name' => $parent->name,
            'slug' => $parent->slug,
            'parent_id' => $child->id,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('parent_id');

    expect($parent->fresh()->parent_id)->toBeNull();
});

it('refuses to delete a category that still has listings', function () {
    $category = Category::factory()->create();
    Listing::factory()->create(['category_id' => $category->id]);

    $this->actingAs(panelAdmin())
        ->from('/admin/categories')
        ->delete("/admin/categories/{$category->id}")
        ->assertSessionHasErrors('category');

    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue();
});

it('refuses to delete a category that still has children', function () {
    $parent = Category::factory()->create();
    Category::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs(panelAdmin())
        ->from('/admin/categories')
        ->delete("/admin/categories/{$parent->id}")
        ->assertSessionHasErrors('category');

    expect(Category::query()->whereKey($parent->id)->exists())->toBeTrue();
});

it('deletes an empty category', function () {
    $category = Category::factory()->create();

    $this->actingAs(panelAdmin())
        ->delete("/admin/categories/{$category->id}")
        ->assertRedirect('/admin/categories');

    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();
});

// ─── leads ──────────────────────────────────────────────────────────────────

it('lists leads and filters to unread', function () {
    $listing = Listing::factory()->published()->create();
    ListingLead::factory()->create(['listing_id' => $listing->id, 'name' => 'Заявка Алфа', 'read_at' => null]);
    ListingLead::factory()->create(['listing_id' => $listing->id, 'name' => 'Заявка Бета', 'read_at' => now()]);

    $this->actingAs(panelAdmin())
        ->get('/admin/leads?unread=1')
        ->assertOk()
        ->assertSee('Заявка Алфа')
        ->assertDontSee('Заявка Бета');
});

it('marks a lead read once and keeps the first timestamp', function () {
    $lead = ListingLead::factory()->create(['read_at' => null]);

    $this->actingAs(panelAdmin())->post("/admin/leads/{$lead->id}/read");
    $first = $lead->fresh()->read_at;
    expect($first)->not->toBeNull();

    $this->travel(5)->minutes();
    $this->actingAs(panelAdmin())->post("/admin/leads/{$lead->id}/read");

    expect($lead->fresh()->read_at->equalTo($first))->toBeTrue();
});

// ─── settings ───────────────────────────────────────────────────────────────

it('falls back to config when a setting has never been saved', function () {
    config()->set('listinghub.moderation.listings_require_approval', true);

    expect(app(SiteSettings::class)->bool('listings_require_approval'))->toBeTrue();
});

it('saves the moderation switches and honours them over config', function () {
    config()->set('listinghub.moderation.listings_require_approval', true);

    $this->actingAs(panelAdmin())->put('/admin/settings', [
        'settings' => ['listings_require_approval' => '0', 'reviews_require_approval' => '1'],
    ])->assertRedirect('/admin/settings');

    expect(app(SiteSettings::class)->bool('listings_require_approval'))->toBeFalse()
        ->and(app(SiteSettings::class)->bool('reviews_require_approval'))->toBeTrue();
});

it('ignores a settings key that is not declared', function () {
    $this->actingAs(panelAdmin())->put('/admin/settings', [
        'settings' => ['listings_require_approval' => '1', 'made_up_key' => '1'],
    ]);

    expect(Setting::query()->where('key', 'made_up_key')->exists())->toBeFalse();
});

it('actually changes member submit behaviour when moderation is switched off', function () {
    $owner = panelMember();
    $owner->forceFill(['email_verified_at' => now()])->save();
    $listing = Listing::factory()->create([
        'user_id' => $owner->id,
        'status' => ListingStatus::Draft->value,
    ]);

    // Moderation ON (config default) → Pending.
    $this->actingAs($owner)->post("/member/listings/{$listing->slug}/submit");
    expect($listing->fresh()->status)->toBe(ListingStatus::Pending);

    // Switch it off through the admin screen, then a second draft goes live.
    $this->actingAs(panelAdmin())->put('/admin/settings', [
        'settings' => ['listings_require_approval' => '0', 'reviews_require_approval' => '1'],
    ]);

    $second = Listing::factory()->create([
        'user_id' => $owner->id,
        'status' => ListingStatus::Draft->value,
    ]);

    $this->actingAs($owner)->post("/member/listings/{$second->slug}/submit");
    expect($second->fresh()->status)->toBe(ListingStatus::Published);
});

// ─── dashboard ──────────────────────────────────────────────────────────────

it('shows counts that reflect the data', function () {
    Listing::factory()->count(2)->create(['status' => ListingStatus::Pending->value]);
    Listing::factory()->published()->create();
    Review::factory()->create(['status' => ModerationStatus::Pending->value]);
    ListingClaim::factory()->create(['status' => ModerationStatus::Pending->value]);
    ListingLead::factory()->create(['read_at' => null]);

    $this->actingAs(panelAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('2 обяви')
        ->assertSee('1 отзива')
        ->assertSee('1 заявки за собственост');
});
