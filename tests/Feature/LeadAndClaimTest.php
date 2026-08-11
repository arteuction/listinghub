<?php

declare(strict_types=1);

use App\Enums\ModerationStatus;
use App\Models\Listing;
use App\Models\ListingClaim;
use App\Models\ListingLead;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
| 3.5.0 — enquiries (leads) and ownership claims.
|
| Key invariants:
|   • A lead may be sent anonymously, but only to a PUBLISHED listing.
|   • Only the listing owner can read its leads; marking read is idempotent.
|   • Submitting a claim NEVER transfers ownership — only approval does.
|   • Approving a claim rejects every other pending claim on that listing.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function claimant(array $attrs = []): User
{
    $user = User::factory()->create(array_merge(['email_verified_at' => now()], $attrs));
    $user->assignRole('member');

    return $user;
}

function claimAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

// ------------------------------------------------------------------- leads

it('captures a lead from an anonymous visitor', function () {
    $listing = Listing::factory()->published()->create();

    $this->post(route('listings.leads.store', $listing->slug), [
        'name' => 'Иван Петров',
        'email' => 'ivan@example.com',
        'message' => 'Интересувам се от услугите ви.',
    ])->assertRedirect();

    $lead = ListingLead::query()->firstOrFail();
    expect($lead->listing_id)->toBe($listing->id)
        ->and($lead->email)->toBe('ivan@example.com')
        ->and($lead->read_at)->toBeNull();
});

it('404s when contacting a non-published listing', function () {
    $draft = Listing::factory()->create();

    $this->post(route('listings.leads.store', $draft->slug), [
        'name' => 'X', 'email' => 'x@example.com', 'message' => 'hi',
    ])->assertNotFound();

    expect(ListingLead::query()->count())->toBe(0);
});

it('validates the lead payload', function () {
    $listing = Listing::factory()->published()->create();

    $this->post(route('listings.leads.store', $listing->slug), [
        'name' => '', 'email' => 'not-an-email', 'message' => '',
    ])->assertSessionHasErrors(['name', 'email', 'message']);
});

it('lets only the owner read a listing\'s leads', function () {
    $owner = claimant();
    $stranger = claimant();
    $listing = Listing::factory()->create(['user_id' => $owner->id]);
    ListingLead::factory()->create(['listing_id' => $listing->id, 'name' => 'Запитване от клиент']);

    $this->actingAs($owner)->get(route('member.listings.leads.index', $listing))
        ->assertOk()->assertSee('Запитване от клиент');

    $this->actingAs($stranger)->get(route('member.listings.leads.index', $listing))->assertForbidden();
});

it('marks a lead read idempotently', function () {
    $owner = claimant();
    $listing = Listing::factory()->create(['user_id' => $owner->id]);
    $lead = ListingLead::factory()->create(['listing_id' => $listing->id]);

    $this->actingAs($owner)->post(route('member.listings.leads.read', [$listing, $lead]))->assertRedirect();
    $first = $lead->fresh()->read_at;
    expect($first)->not->toBeNull();

    // Re-marking must not move the timestamp.
    $this->actingAs($owner)->post(route('member.listings.leads.read', [$listing, $lead]))->assertRedirect();
    expect($lead->fresh()->read_at->equalTo($first))->toBeTrue();
});

it('rejects a lead id belonging to another listing', function () {
    $owner = claimant();
    $listingA = Listing::factory()->create(['user_id' => $owner->id]);
    $listingB = Listing::factory()->create(['user_id' => $owner->id]);
    $leadB = ListingLead::factory()->create(['listing_id' => $listingB->id]);

    $this->actingAs($owner)->post(route('member.listings.leads.read', [$listingA, $leadB]))->assertNotFound();
});

// ------------------------------------------------------------------ claims

it('opens a pending claim without transferring ownership', function () {
    $owner = claimant();
    $challenger = claimant();
    $listing = Listing::factory()->published()->create(['user_id' => $owner->id]);

    $this->actingAs($challenger)->post(route('listings.claims.store', $listing->slug), [
        'message' => 'Това е моят бизнес.',
    ])->assertRedirect();

    $claim = ListingClaim::query()->firstOrFail();
    expect($claim->status)->toBe(ModerationStatus::Pending)
        // Ownership must be untouched until a moderator decides.
        ->and((int) $listing->fresh()->user_id)->toBe((int) $owner->id);
});

it('refuses a claim on a listing the user already owns', function () {
    $owner = claimant();
    $listing = Listing::factory()->published()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('listings.claims.store', $listing->slug), [
        'message' => 'моя е',
    ])->assertSessionHasErrors('message');

    expect(ListingClaim::query()->count())->toBe(0);
});

it('refuses a duplicate pending claim from the same user', function () {
    $owner = claimant();
    $challenger = claimant();
    $listing = Listing::factory()->published()->create(['user_id' => $owner->id]);
    ListingClaim::factory()->create(['listing_id' => $listing->id, 'user_id' => $challenger->id]);

    $this->actingAs($challenger)->post(route('listings.claims.store', $listing->slug), [
        'message' => 'пак аз',
    ])->assertSessionHasErrors('message');

    expect(ListingClaim::query()->count())->toBe(1);
});

it('transfers the listing when a moderator approves a claim', function () {
    $admin = claimAdmin();
    $owner = claimant();
    $challenger = claimant();
    $listing = Listing::factory()->published()->create(['user_id' => $owner->id]);
    $claim = ListingClaim::factory()->create(['listing_id' => $listing->id, 'user_id' => $challenger->id]);

    $this->actingAs($admin)->post(route('admin.moderation.claims.decide', $claim), [
        'decision' => 'approve',
    ])->assertRedirect(route('admin.moderation.index'));

    expect($claim->fresh()->status)->toBe(ModerationStatus::Approved)
        ->and((int) $listing->fresh()->user_id)->toBe((int) $challenger->id);
});

it('rejects the other pending claims when one is approved', function () {
    $admin = claimAdmin();
    $owner = claimant();
    $winner = claimant();
    $loser = claimant();
    $listing = Listing::factory()->published()->create(['user_id' => $owner->id]);
    $winning = ListingClaim::factory()->create(['listing_id' => $listing->id, 'user_id' => $winner->id]);
    $losing = ListingClaim::factory()->create(['listing_id' => $listing->id, 'user_id' => $loser->id]);

    $this->actingAs($admin)->post(route('admin.moderation.claims.decide', $winning), ['decision' => 'approve']);

    expect($losing->fresh()->status)->toBe(ModerationStatus::Rejected)
        ->and((int) $listing->fresh()->user_id)->toBe((int) $winner->id);
});

it('leaves ownership alone when a claim is rejected', function () {
    $admin = claimAdmin();
    $owner = claimant();
    $challenger = claimant();
    $listing = Listing::factory()->published()->create(['user_id' => $owner->id]);
    $claim = ListingClaim::factory()->create(['listing_id' => $listing->id, 'user_id' => $challenger->id]);

    $this->actingAs($admin)->post(route('admin.moderation.claims.decide', $claim), ['decision' => 'reject']);

    expect($claim->fresh()->status)->toBe(ModerationStatus::Rejected)
        ->and((int) $listing->fresh()->user_id)->toBe((int) $owner->id);
});
