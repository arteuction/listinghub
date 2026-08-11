<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Enums\ListingTransition;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
| RequestChanges: Pending → Draft with a MANDATORY reason.
|
| This is the "reject" of the closed transition map — the listing goes back
| to the owner editable and resubmittable instead of dying in a terminal
| status. The reason is stored on the listing, shown to the owner while the
| draft sits with them, and cleared on resubmission (a resubmitted listing
| is a new review; a stale banner helps no one).
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function rcAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

function rcMember(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('member');

    return $user;
}

it('is part of the legal map from Pending only', function () {
    expect(ListingTransition::availableFrom(ListingStatus::Pending))
        ->toContain(ListingTransition::RequestChanges)
        ->and(ListingTransition::availableFrom(ListingStatus::Published))
        ->not->toContain(ListingTransition::RequestChanges)
        ->and(ListingTransition::RequestChanges->requiresReason())->toBeTrue();
});

it('returns a pending listing to draft and stores the reason', function () {
    $owner = rcMember();
    $listing = Listing::factory()->create(['user_id' => $owner->id, 'status' => 'pending']);

    $this->actingAs(rcAdmin())->post(route('admin.listings.transition', $listing), [
        'transition' => 'request_changes',
        'reason' => 'Липсва адрес и телефон за контакт.',
    ])->assertSessionHasNoErrors();

    $listing->refresh();

    expect($listing->status)->toBe(ListingStatus::Draft)
        ->and($listing->moderation_note)->toBe('Липсва адрес и телефон за контакт.');
});

it('refuses the move without a reason and changes nothing', function () {
    $owner = rcMember();
    $listing = Listing::factory()->create(['user_id' => $owner->id, 'status' => 'pending']);

    $this->actingAs(rcAdmin())->post(route('admin.listings.transition', $listing), [
        'transition' => 'request_changes',
    ])->assertSessionHasErrors('reason');

    expect($listing->refresh()->status)->toBe(ListingStatus::Pending);
});

it('refuses the move from a published listing', function () {
    $owner = rcMember();
    $listing = Listing::factory()->published()->create(['user_id' => $owner->id]);

    $this->actingAs(rcAdmin())->post(route('admin.listings.transition', $listing), [
        'transition' => 'request_changes',
        'reason' => 'някаква причина',
    ])->assertSessionHasErrors('transition');

    expect($listing->refresh()->status)->toBe(ListingStatus::Published);
});

it('shows the reason to the owner while the draft is with them', function () {
    $owner = rcMember();
    $listing = Listing::factory()->create([
        'user_id' => $owner->id,
        'status' => 'draft',
        'moderation_note' => 'Снимките са с прекалено ниско качество.',
    ]);

    $this->actingAs($owner)->get(route('member.listings.index'))
        ->assertOk()
        ->assertSee('Върната за корекции')
        ->assertSee('Снимките са с прекалено ниско качество.');
});

it('clears the note when the owner resubmits', function () {
    $owner = rcMember();
    $listing = Listing::factory()->create([
        'user_id' => $owner->id,
        'status' => 'draft',
        'moderation_note' => 'Липсва описание.',
    ]);

    $this->actingAs($owner)->post(route('member.listings.submit', $listing))
        ->assertSessionHasNoErrors();

    $listing->refresh();

    expect($listing->status)->not->toBe(ListingStatus::Draft)
        ->and($listing->moderation_note)->toBeNull();
});

it('keeps the full moderation cycle sound: pending → draft → pending → published', function () {
    $owner = rcMember();
    $admin = rcAdmin();
    $listing = Listing::factory()->create(['user_id' => $owner->id, 'status' => 'pending']);

    $this->actingAs($admin)->post(route('admin.listings.transition', $listing), [
        'transition' => 'request_changes', 'reason' => 'Коригирайте категорията.',
    ]);
    expect($listing->refresh()->status)->toBe(ListingStatus::Draft);

    $this->actingAs($owner)->post(route('member.listings.submit', $listing));
    $listing->refresh();
    expect($listing->moderation_note)->toBeNull();

    if ($listing->status === ListingStatus::Pending) {
        $this->actingAs($admin)->post(route('admin.listings.transition', $listing), [
            'transition' => 'approve',
        ]);
        expect($listing->refresh()->status)->toBe(ListingStatus::Published);
    } else {
        // Moderation was off — AutoPublish path.
        expect($listing->status)->toBe(ListingStatus::Published);
    }
});
