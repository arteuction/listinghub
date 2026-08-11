<?php

declare(strict_types=1);

use App\Actions\Listings\TransitionListing;
use App\Enums\ListingStatus;
use App\Enums\ListingTransition;
use App\Exceptions\InvalidListingTransition;
use App\Models\Listing;

beforeEach(function () {
    $this->action = new TransitionListing;
});

it('performs all six legal transitions', function (ListingTransition $t, ListingStatus $from, ListingStatus $to) {
    $listing = Listing::factory()->create(['status' => $from]);

    expect($this->action->handle($listing, $t)->status)->toBe($to);
    expect($listing->fresh()->status)->toBe($to);
})->with([
    'submit' => [ListingTransition::Submit, ListingStatus::Draft, ListingStatus::Pending],
    'auto_publish' => [ListingTransition::AutoPublish, ListingStatus::Draft, ListingStatus::Published],
    'approve' => [ListingTransition::Approve, ListingStatus::Pending, ListingStatus::Published],
    'disapprove' => [ListingTransition::Disapprove, ListingStatus::Published, ListingStatus::Pending],
    'suspend' => [ListingTransition::Suspend, ListingStatus::Published, ListingStatus::Suspended],
    'restore' => [ListingTransition::Restore, ListingStatus::Suspended, ListingStatus::Published],
]);

it('rejects an illegal transition and leaves the database unchanged', function () {
    $listing = Listing::factory()->create(['status' => ListingStatus::Draft]);

    expect(fn () => $this->action->handle($listing, ListingTransition::Suspend))
        ->toThrow(InvalidListingTransition::class);

    expect($listing->fresh()->status)->toBe(ListingStatus::Draft);
});

it('re-reads the locked status so a stale instance cannot bypass the map', function () {
    $listing = Listing::factory()->create(['status' => ListingStatus::Draft]);

    // A concurrent writer publishes it directly in the DB.
    Listing::whereKey($listing->getKey())->update(['status' => ListingStatus::Published->value]);

    // Our in-memory copy still says Draft, so Submit would look legal on it —
    // but the action must guard against the locked DB status.
    expect(fn () => $this->action->handle($listing, ListingTransition::Submit))
        ->toThrow(InvalidListingTransition::class);

    expect($listing->fresh()->status)->toBe(ListingStatus::Published);
});

it('stamps published_at only on the first publish and keeps it stable', function () {
    $listing = Listing::factory()->create(['status' => ListingStatus::Draft, 'published_at' => null]);

    $this->action->handle($listing, ListingTransition::AutoPublish);
    $firstPublishedAt = $listing->fresh()->published_at;
    expect($firstPublishedAt)->not->toBeNull();

    // Suspend then restore must not clear or move published_at.
    $this->action->handle($listing->fresh(), ListingTransition::Suspend);
    expect($listing->fresh()->published_at?->equalTo($firstPublishedAt))->toBeTrue();

    $this->action->handle($listing->fresh(), ListingTransition::Restore);
    expect($listing->fresh()->published_at?->equalTo($firstPublishedAt))->toBeTrue();
});
