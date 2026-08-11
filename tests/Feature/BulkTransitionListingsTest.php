<?php

declare(strict_types=1);

use App\Actions\Listings\BulkTransitionListings;
use App\Enums\ListingStatus;
use App\Enums\ListingTransition;
use App\Models\Listing;

beforeEach(function () {
    $this->bulk = app(BulkTransitionListings::class);
});

it('transitions every eligible listing and reports the rest', function () {
    $pending = Listing::factory()->count(3)->create(['status' => ListingStatus::Pending]);
    $draft = Listing::factory()->create(['status' => ListingStatus::Draft]); // Approve is illegal from Draft

    $result = $this->bulk->handle(
        $pending->pluck('id')->push($draft->id)->all(),
        ListingTransition::Approve,
    );

    expect($result['transitioned'])->toHaveCount(3)
        ->and($result['failed'])->toHaveCount(1)
        ->and($result['failed'][0]['id'])->toBe($draft->id);

    foreach ($pending as $listing) {
        expect($listing->fresh()->status)->toBe(ListingStatus::Published);
    }
    // The illegal one is untouched.
    expect($draft->fresh()->status)->toBe(ListingStatus::Draft);
});

it('isolates failures — a bad row never rolls back a good one', function () {
    $published = Listing::factory()->create(['status' => ListingStatus::Published]);
    $draft = Listing::factory()->create(['status' => ListingStatus::Draft]);

    // Suspend is legal from Published, illegal from Draft.
    $result = $this->bulk->handle([$published->id, $draft->id], ListingTransition::Suspend);

    expect($published->fresh()->status)->toBe(ListingStatus::Suspended)
        ->and($draft->fresh()->status)->toBe(ListingStatus::Draft)
        ->and($result['transitioned'])->toBe([$published->id])
        ->and($result['failed'][0]['id'])->toBe($draft->id);
});

it('reports missing ids without aborting the batch', function () {
    $pending = Listing::factory()->create(['status' => ListingStatus::Pending]);

    $result = $this->bulk->handle([$pending->id, 999999], ListingTransition::Approve);

    expect($result['transitioned'])->toBe([$pending->id])
        ->and($result['failed'])->toHaveCount(1)
        ->and($result['failed'][0]['reason'])->toContain('not found');
});

it('ignores duplicate and invalid ids', function () {
    $pending = Listing::factory()->create(['status' => ListingStatus::Pending]);

    $result = $this->bulk->handle([$pending->id, $pending->id, 0, -5], ListingTransition::Approve);

    expect($result['transitioned'])->toBe([$pending->id]) // deduped, applied once
        ->and($result['failed'])->toBeEmpty();
});

it('returns empty results for an empty batch', function () {
    $result = $this->bulk->handle([], ListingTransition::Approve);

    expect($result['transitioned'])->toBeEmpty()
        ->and($result['failed'])->toBeEmpty();
});
