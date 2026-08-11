<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Config;

/*
| 3.4.0 — ownership. Every assertion here is about one member being unable to
| reach another member's row, and about publishing going through the closed
| transition map rather than a direct status write.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function owner(array $attrs = []): User
{
    $user = User::factory()->create(array_merge(['email_verified_at' => now()], $attrs));
    $user->assignRole('member');

    return $user;
}

// ------------------------------------------------------------------ creation

it('requires a verified email before a listing can be created', function () {
    $unverified = owner(['email_verified_at' => null]);

    $this->actingAs($unverified)->get(route('member.listings.create'))->assertForbidden();

    $this->actingAs($unverified)->post(route('member.listings.store'), [
        'title' => 'Опит',
        'category_id' => Category::factory()->create()->id,
    ])->assertForbidden();

    expect(Listing::query()->count())->toBe(0);
});

it('creates a listing as a draft owned by the member', function () {
    $user = owner();
    $category = Category::factory()->create();

    $this->actingAs($user)->post(route('member.listings.store'), [
        'title' => 'Пекарна Слънце',
        'category_id' => $category->id,
        'description' => 'Топъл хляб всеки ден.',
    ])->assertRedirect();

    $listing = Listing::query()->firstOrFail();

    expect($listing->user_id)->toBe($user->id)
        ->and($listing->status)->toBe(ListingStatus::Draft)
        ->and($listing->published_at)->toBeNull()
        // The exact Cyrillic transliteration is Str::slug's business; what
        // matters is that the result is a URL-safe, non-empty slug.
        ->and($listing->slug)->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*$/');
});

it('never lets a member set the owner or the status directly', function () {
    $user = owner();
    $victim = owner(['email' => 'victim@example.com']);

    $this->actingAs($user)->post(route('member.listings.store'), [
        'title' => 'Опит за присвояване',
        'category_id' => Category::factory()->create()->id,
        // Both are ignored: neither is in the validated set, and the action
        // writes user_id and status itself.
        'user_id' => $victim->id,
        'status' => ListingStatus::Published->value,
    ])->assertRedirect();

    $listing = Listing::query()->firstOrFail();

    expect($listing->user_id)->toBe($user->id)
        ->and($listing->status)->toBe(ListingStatus::Draft);
});

it('rejects a website URL with a dangerous scheme', function () {
    $user = owner();

    $this->actingAs($user)->from(route('member.listings.create'))->post(route('member.listings.store'), [
        'title' => 'Обява',
        'category_id' => Category::factory()->create()->id,
        'website' => 'javascript:alert(1)',
    ])->assertSessionHasErrors('website');

    expect(Listing::query()->count())->toBe(0);
});

it('rejects a settlement id that is not in the Bulgarian dataset', function () {
    $user = owner();

    $this->actingAs($user)->from(route('member.listings.create'))->post(route('member.listings.store'), [
        'title' => 'Обява',
        'category_id' => Category::factory()->create()->id,
        'settlement_id' => 999999,
    ])->assertSessionHasErrors('settlement_id');
});

// ------------------------------------------------------------------ ownership

it('refuses every write on a listing owned by someone else', function (string $method, string $route) {
    $mine = owner();
    $theirs = Listing::factory()->create(['user_id' => owner(['email' => 'other@example.com'])->id]);

    $this->actingAs($mine)->call($method, route($route, $theirs), [
        'title' => 'Присвоено',
        'category_id' => $theirs->category_id,
    ])->assertForbidden();

    expect($theirs->fresh()->title)->not->toBe('Присвоено')
        ->and($theirs->fresh()->deleted_at)->toBeNull();
})->with([
    'edit' => ['GET', 'member.listings.edit'],
    'update' => ['PUT', 'member.listings.update'],
    'submit' => ['POST', 'member.listings.submit'],
    'delete' => ['DELETE', 'member.listings.destroy'],
]);

it('lists only the members own listings', function () {
    $mine = owner();
    Listing::factory()->create(['user_id' => $mine->id, 'title' => 'Моята обява']);
    Listing::factory()->create([
        'user_id' => owner(['email' => 'other@example.com'])->id,
        'title' => 'Чужда обява',
    ]);

    $this->actingAs($mine)->get(route('member.listings.index'))
        ->assertOk()
        ->assertSee('Моята обява')
        ->assertDontSee('Чужда обява');
});

it('lets the owner edit and soft-delete their own listing', function () {
    $user = owner();
    $listing = Listing::factory()->create(['user_id' => $user->id, 'title' => 'Старо име']);

    $this->actingAs($user)->put(route('member.listings.update', $listing), [
        'title' => 'Ново име',
        'category_id' => $listing->category_id,
    ])->assertRedirect();

    expect($listing->fresh()->title)->toBe('Ново име');

    $this->actingAs($user)->delete(route('member.listings.destroy', $listing))->assertRedirect();

    expect($listing->fresh())->toBeNull()
        ->and(Listing::withTrashed()->whereKey($listing->getKey())->exists())->toBeTrue();
});

it('keeps the slug when the title changes so indexed URLs survive', function () {
    $user = owner();
    $listing = Listing::factory()->create(['user_id' => $user->id, 'slug' => 'stariyat-slug']);

    $this->actingAs($user)->put(route('member.listings.update', $listing), [
        'title' => 'Съвсем различно заглавие',
        'category_id' => $listing->category_id,
    ])->assertRedirect();

    expect($listing->fresh()->slug)->toBe('stariyat-slug');
});

// ----------------------------------------------------------------- publishing

it('submits a draft for approval when moderation is on', function () {
    Config::set('listinghub.moderation.listings_require_approval', true);
    $user = owner();
    $listing = Listing::factory()->create(['user_id' => $user->id, 'status' => ListingStatus::Draft]);

    $this->actingAs($user)->post(route('member.listings.submit', $listing))->assertRedirect();

    expect($listing->fresh()->status)->toBe(ListingStatus::Pending);
});

it('publishes straight away when moderation is off', function () {
    Config::set('listinghub.moderation.listings_require_approval', false);
    $user = owner();
    $listing = Listing::factory()->create(['user_id' => $user->id, 'status' => ListingStatus::Draft]);

    $this->actingAs($user)->post(route('member.listings.submit', $listing))->assertRedirect();

    expect($listing->fresh()->status)->toBe(ListingStatus::Published);
});

it('refuses to submit a listing that is not a draft', function (string $status) {
    $user = owner();
    $listing = Listing::factory()->create(['user_id' => $user->id, 'status' => $status]);

    $this->actingAs($user)->post(route('member.listings.submit', $listing))->assertForbidden();

    expect($listing->fresh()->status->value)->toBe($status);
})->with([
    'pending' => [ListingStatus::Pending->value],
    'published' => [ListingStatus::Published->value],
    'suspended' => [ListingStatus::Suspended->value],
]);

// ------------------------------------------------------------------ favorites

it('saves and removes a published listing', function () {
    $user = owner();
    $listing = Listing::factory()->published()->create();

    $this->actingAs($user)->post(route('member.favorites.store', $listing))->assertRedirect();
    expect($user->favorites()->count())->toBe(1);

    // Idempotent: a replayed submit must not hit the unique index.
    $this->actingAs($user)->post(route('member.favorites.store', $listing))->assertRedirect();
    expect($user->favorites()->count())->toBe(1);

    $this->actingAs($user)->delete(route('member.favorites.destroy', $listing))->assertRedirect();
    expect($user->favorites()->count())->toBe(0);
});

it('refuses to save a listing that is not published', function (string $status) {
    $user = owner();
    $listing = Listing::factory()->create(['status' => $status]);

    $this->actingAs($user)->post(route('member.favorites.store', $listing))->assertNotFound();

    expect($user->favorites()->count())->toBe(0);
})->with([
    'draft' => [ListingStatus::Draft->value],
    'pending' => [ListingStatus::Pending->value],
    'suspended' => [ListingStatus::Suspended->value],
]);

it('hides a favourite once it stops being published', function () {
    $user = owner();
    $listing = Listing::factory()->published()->create(['title' => 'Запазена обява']);

    $this->actingAs($user)->post(route('member.favorites.store', $listing));
    $this->actingAs($user)->get(route('member.favorites.index'))->assertSee('Запазена обява');

    $listing->update(['status' => ListingStatus::Suspended]);

    $this->actingAs($user)->get(route('member.favorites.index'))->assertDontSee('Запазена обява');
});

it('requires authentication for the member area', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with([
    'listings' => ['member.listings.index'],
    'favorites' => ['member.favorites.index'],
]);

// -------------------------------------------------------------- geo lookup

it('returns only the settlements of the requested region', function () {
    $sofia = App\Models\Region::factory()->create(['slug' => 'sofia-grad']);
    $sofiaMuni = App\Models\Municipality::factory()->create(['region_id' => $sofia->id]);
    App\Models\Settlement::factory()->create(['municipality_id' => $sofiaMuni->id, 'name' => 'София']);

    $varna = App\Models\Region::factory()->create(['slug' => 'varna']);
    $varnaMuni = App\Models\Municipality::factory()->create(['region_id' => $varna->id]);
    App\Models\Settlement::factory()->create(['municipality_id' => $varnaMuni->id, 'name' => 'Варна']);

    $res = $this->getJson(route('regions.settlements', 'sofia-grad'))->assertOk();

    $names = array_column($res->json(), 'name');

    expect($names)->toContain('София')->and($names)->not->toContain('Варна');
});
