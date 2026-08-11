<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingHour;
use App\Models\ListingHourException;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
| 3.4.2 — Member/Admin CRUD for a listing's weekly hours + date exceptions.
|
| Key invariants:
|   • SyncListingHours is a full replacement: a day omitted from the payload
|     is deleted, not left unchanged.
|   • A day marked closed cannot carry opens_at/closes_at (validation).
|   • opens_at/closes_at must both be present, and closes_at after opens_at.
|   • Exceptions belong to exactly one listing; cross-listing ids 404.
|   • Ownership: a stranger cannot touch another member's listing hours.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function hoursOwner(array $attrs = []): User
{
    $user = User::factory()->create(array_merge(['email_verified_at' => now()], $attrs));
    $user->assignRole('member');

    return $user;
}

it('replaces the weekly schedule and drops days omitted from the payload', function () {
    $user = hoursOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);
    ListingHour::factory()->for($listing)->create(['day_of_week' => 2]); // Tuesday, pre-existing

    $this->actingAs($user)->put(route('member.listings.hours.update', $listing), [
        'days' => [
            ['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00', 'is_closed' => '0'],
            ['day_of_week' => 3, 'is_closed' => '1'],
        ],
    ])->assertRedirect(route('member.listings.hours.edit', $listing));

    $rows = ListingHour::query()->where('listing_id', $listing->id)->orderBy('day_of_week')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('day_of_week')->all())->toBe([1, 3])
        // TIME columns normalise differently per driver (MySQL always returns
        // H:i:s; SQLite has no native TIME type and echoes back exactly what
        // was stored), so compare the H:i prefix rather than the raw string.
        ->and(str_starts_with((string) $rows->firstWhere('day_of_week', 1)->opens_at, '09:00'))->toBeTrue()
        ->and($rows->firstWhere('day_of_week', 3)->is_closed)->toBeTrue();

    // Tuesday (day_of_week 2) was NOT in the new payload — full replacement removed it.
    expect(ListingHour::query()->where('listing_id', $listing->id)->where('day_of_week', 2)->exists())->toBeFalse();
});

it('rejects a day with only one of opens_at/closes_at set', function () {
    $user = hoursOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put(route('member.listings.hours.update', $listing), [
        'days' => [
            ['day_of_week' => 1, 'opens_at' => '09:00', 'is_closed' => '0'],
        ],
    ])->assertSessionHasErrors('days.0.closes_at');
});

it('rejects closes_at at or before opens_at', function () {
    $user = hoursOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put(route('member.listings.hours.update', $listing), [
        'days' => [
            ['day_of_week' => 1, 'opens_at' => '18:00', 'closes_at' => '09:00', 'is_closed' => '0'],
        ],
    ])->assertSessionHasErrors('days.0.closes_at');
});

it('rejects a duplicate day_of_week in the same payload', function () {
    $user = hoursOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put(route('member.listings.hours.update', $listing), [
        'days' => [
            ['day_of_week' => 1, 'is_closed' => '1'],
            ['day_of_week' => 1, 'is_closed' => '1'],
        ],
    ])->assertSessionHasErrors('days.1.day_of_week');
});

it('blocks a stranger from replacing another owner\'s hours', function () {
    $owner = hoursOwner();
    $stranger = hoursOwner();
    $listing = Listing::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)->put(route('member.listings.hours.update', $listing), [
        'days' => [['day_of_week' => 1, 'is_closed' => '1']],
    ])->assertForbidden();

    $this->actingAs($stranger)->get(route('member.listings.hours.edit', $listing))->assertForbidden();
});

// ------------------------------------------------------------------ exceptions

it('creates a closed-all-day exception by default', function () {
    $user = hoursOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('member.listings.hours.exceptions.store', $listing), [
        'date' => '2026-12-25',
        'note' => 'Коледа',
    ])->assertRedirect(route('member.listings.hours.edit', $listing));

    $exception = ListingHourException::query()->where('listing_id', $listing->id)->firstOrFail();
    expect($exception->is_closed)->toBeTrue()
        ->and($exception->opens_at)->toBeNull()
        ->and($exception->note)->toBe('Коледа');
});

it('creates an open exception when both times are given and is_closed=0', function () {
    $user = hoursOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('member.listings.hours.exceptions.store', $listing), [
        'date' => '2026-12-24',
        'is_closed' => '0',
        'opens_at' => '10:00',
        'closes_at' => '14:00',
    ])->assertRedirect();

    $exception = ListingHourException::query()->where('listing_id', $listing->id)->firstOrFail();
    expect($exception->is_closed)->toBeFalse()
        ->and(str_starts_with((string) $exception->opens_at, '10:00'))->toBeTrue();
});

it('deletes an exception belonging to the listing', function () {
    $user = hoursOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);
    $exception = ListingHourException::factory()->for($listing)->create();

    $this->actingAs($user)->delete(route('member.listings.hours.exceptions.destroy', [$listing, $exception]))
        ->assertRedirect(route('member.listings.hours.edit', $listing));

    expect(ListingHourException::query()->find($exception->id))->toBeNull();
});

it('rejects an exception id that belongs to a different listing', function () {
    $user = hoursOwner();
    $listingA = Listing::factory()->create(['user_id' => $user->id]);
    $listingB = Listing::factory()->create(['user_id' => $user->id]);
    $exceptionB = ListingHourException::factory()->for($listingB)->create();

    $this->actingAs($user)->delete(route('member.listings.hours.exceptions.destroy', [$listingA, $exceptionB]))
        ->assertNotFound();
});
