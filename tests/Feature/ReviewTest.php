<?php

declare(strict_types=1);

use App\Enums\ModerationStatus;
use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Config;

/*
| 3.5.0 — reviews.
|
| Key invariants:
|   • Only an APPROVED review is publicly visible and counted in rating_avg.
|   • rating_avg/rating_count are DERIVED from approved rows, so a rejection
|     (or a reversed decision) moves the average back.
|   • One review per user per listing; an owner cannot review their own.
|   • A non-published listing cannot be reviewed and must 404, not 403.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function reviewer(array $attrs = []): User
{
    $user = User::factory()->create(array_merge(['email_verified_at' => now()], $attrs));
    $user->assignRole('member');

    return $user;
}

function reviewAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

it('stores a review as pending when moderation is on', function () {
    Config::set('listinghub.moderation.reviews_require_approval', true);

    $user = reviewer();
    $listing = Listing::factory()->published()->create();

    $this->actingAs($user)->post(route('listings.reviews.store', $listing->slug), [
        'rating' => 5, 'body' => 'Чудесно място',
    ])->assertRedirect();

    $review = Review::query()->firstOrFail();
    expect($review->status)->toBe(ModerationStatus::Pending)
        // A pending review must not move the public score.
        ->and((int) $listing->fresh()->rating_count)->toBe(0);
});

it('auto-approves and updates the aggregate when moderation is off', function () {
    Config::set('listinghub.moderation.reviews_require_approval', false);

    $user = reviewer();
    $listing = Listing::factory()->published()->create();

    $this->actingAs($user)->post(route('listings.reviews.store', $listing->slug), [
        'rating' => 4,
    ])->assertRedirect();

    $listing->refresh();
    expect(Review::query()->firstOrFail()->status)->toBe(ModerationStatus::Approved)
        ->and((int) $listing->rating_count)->toBe(1)
        ->and((float) $listing->rating_avg)->toBe(4.0);
});

it('rejects a second review from the same user', function () {
    $user = reviewer();
    $listing = Listing::factory()->published()->create();
    Review::factory()->create(['listing_id' => $listing->id, 'user_id' => $user->id]);

    $this->actingAs($user)->post(route('listings.reviews.store', $listing->slug), [
        'rating' => 3,
    ])->assertSessionHasErrors('rating');

    expect(Review::query()->count())->toBe(1);
});

it('forbids an owner reviewing their own listing', function () {
    $user = reviewer();
    $listing = Listing::factory()->published()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('listings.reviews.store', $listing->slug), [
        'rating' => 5,
    ])->assertSessionHasErrors('rating');

    expect(Review::query()->count())->toBe(0);
});

it('404s when reviewing a non-published listing', function () {
    $user = reviewer();
    $draft = Listing::factory()->create(); // draft

    $this->actingAs($user)->post(route('listings.reviews.store', $draft->slug), [
        'rating' => 5,
    ])->assertNotFound();
});

it('requires a verified email to review', function () {
    $unverified = reviewer(['email_verified_at' => null]);
    $listing = Listing::factory()->published()->create();

    $this->actingAs($unverified)->post(route('listings.reviews.store', $listing->slug), [
        'rating' => 5,
    ])->assertForbidden();
});

it('validates the rating range', function () {
    $user = reviewer();
    $listing = Listing::factory()->published()->create();

    $this->actingAs($user)->post(route('listings.reviews.store', $listing->slug), [
        'rating' => 6,
    ])->assertSessionHasErrors('rating');
});

it('shows only approved reviews on the public page', function () {
    $listing = Listing::factory()->published()->create();
    Review::factory()->approved()->create(['listing_id' => $listing->id, 'body' => 'Одобрен отзив']);
    Review::factory()->create(['listing_id' => $listing->id, 'body' => 'Чакащ отзив']);

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertSee('Одобрен отзив')
        ->assertDontSee('Чакащ отзив');
});

// -------------------------------------------------------------- moderation

it('recomputes the aggregate when a moderator approves a review', function () {
    $admin = reviewAdmin();
    $listing = Listing::factory()->published()->create();
    $review = Review::factory()->create(['listing_id' => $listing->id, 'rating' => 5]);

    $this->actingAs($admin)->post(route('admin.moderation.reviews.decide', $review), [
        'decision' => 'approve',
    ])->assertRedirect(route('admin.moderation.index'));

    $listing->refresh();
    expect($review->fresh()->status)->toBe(ModerationStatus::Approved)
        ->and((int) $listing->rating_count)->toBe(1)
        ->and((float) $listing->rating_avg)->toBe(5.0);
});

it('moves the average back when an approved review is later rejected', function () {
    $admin = reviewAdmin();
    $listing = Listing::factory()->published()->create();
    $review = Review::factory()->approved()->create(['listing_id' => $listing->id, 'rating' => 5]);

    // Bring the aggregate up to date first.
    $this->actingAs($admin)->post(route('admin.moderation.reviews.decide', $review), ['decision' => 'approve']);
    expect((int) $listing->fresh()->rating_count)->toBe(1);

    // Reversing the decision must remove those stars from the public score.
    $this->actingAs($admin)->post(route('admin.moderation.reviews.decide', $review), ['decision' => 'reject']);

    $listing->refresh();
    expect($review->fresh()->status)->toBe(ModerationStatus::Rejected)
        ->and((int) $listing->rating_count)->toBe(0)
        ->and((float) $listing->rating_avg)->toBe(0.0);
});

it('treats an unrecognised decision as a rejection, never an approval', function () {
    $admin = reviewAdmin();
    $listing = Listing::factory()->published()->create();
    $review = Review::factory()->create(['listing_id' => $listing->id]);

    $this->actingAs($admin)->post(route('admin.moderation.reviews.decide', $review), [
        'decision' => 'something-else',
    ])->assertRedirect();

    expect($review->fresh()->status)->toBe(ModerationStatus::Rejected);
});

it('blocks a plain member from the moderation queue', function () {
    $user = reviewer();

    $this->actingAs($user)->get(route('admin.moderation.index'))->assertForbidden();
});
