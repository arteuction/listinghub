<?php

declare(strict_types=1);

use App\Actions\Media\RepositionMediaAsset;
use App\Models\Listing;
use App\Models\MediaAsset;

beforeEach(function () {
    $this->action = new RepositionMediaAsset;
});

/** Create $n gallery assets on a listing at 1000, 2000, ... */
function gallery(Listing $listing, int $n): array
{
    $assets = [];
    for ($i = 1; $i <= $n; $i++) {
        $assets[] = MediaAsset::factory()->forOwner($listing, $i * 1000)->create();
    }

    return $assets;
}

it('inserts at the midpoint when a gap exists', function () {
    $listing = Listing::factory()->create();
    [$first, $second, $third] = gallery($listing, 3);

    $this->action->handle($third, $first); // move third to just after first

    expect($third->fresh()->sort_order)->toBe(1500);

    $order = $listing->media()->pluck('id')->all();
    expect($order)->toBe([$first->id, $third->id, $second->id]);
});

it('moves an asset to the front', function () {
    $listing = Listing::factory()->create();
    [$first, $second] = gallery($listing, 2);

    $this->action->handle($second, null);

    expect($second->fresh()->sort_order)->toBe(500)
        ->and($listing->media()->pluck('id')->first())->toBe($second->id);
});

it('rebalances the scope when no gap remains, preserving order', function () {
    $listing = Listing::factory()->create();
    $a = MediaAsset::factory()->forOwner($listing, 1000)->create();
    $b = MediaAsset::factory()->forOwner($listing, 1001)->create();
    $c = MediaAsset::factory()->forOwner($listing, 5000)->create();

    $this->action->handle($c, $a); // wants to sit between 1000 and 1001 -> rebalance

    $order = $listing->media()->orderBy('sort_order')->pluck('id')->all();
    expect($order)->toBe([$a->id, $c->id, $b->id]);

    $positions = $listing->media()->orderBy('sort_order')->pluck('sort_order')->all();
    expect($positions)->toBe([1000, 2000, 3000]);
});

it('never touches another listing\'s gallery', function () {
    $one = Listing::factory()->create();
    $two = Listing::factory()->create();
    [$o1, $o2, $o3] = gallery($one, 3);
    [$t1, $t2, $t3] = gallery($two, 3);

    $this->action->handle($o3, $o1);

    // Listing two is untouched.
    expect($t1->fresh()->sort_order)->toBe(1000)
        ->and($t2->fresh()->sort_order)->toBe(2000)
        ->and($t3->fresh()->sort_order)->toBe(3000);
});

it('resolves neighbours by collection index, not numeric comparison, when positions are equal', function () {
    $listing = Listing::factory()->create();
    // Three assets sharing the default sort_order 1000; order is decided by id.
    $a = MediaAsset::factory()->forOwner($listing, 1000)->create();
    $b = MediaAsset::factory()->forOwner($listing, 1000)->create();
    $c = MediaAsset::factory()->forOwner($listing, 1000)->create();

    $this->action->handle($c, $a); // move c right after a -> rebalance, order a,c,b

    $order = $listing->media()->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
    expect($order)->toBe([$a->id, $c->id, $b->id]);
    expect($listing->media()->orderBy('sort_order')->pluck('sort_order')->all())
        ->toBe([1000, 2000, 3000]);
});

it('is a no-op when the target is the asset itself', function () {
    $listing = Listing::factory()->create();
    [$first, $second] = gallery($listing, 2);

    $result = $this->action->handle($second, $second);

    expect($result->sort_order)->toBe(2000)
        ->and($second->fresh()->sort_order)->toBe(2000);
});

it('rejects a target from a different scope', function () {
    $one = Listing::factory()->create();
    $two = Listing::factory()->create();
    [$a] = gallery($one, 1);
    [$b] = gallery($two, 1);

    expect(fn () => $this->action->handle($a, $b))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a target that no longer exists', function () {
    $listing = Listing::factory()->create();
    [$a, $b] = gallery($listing, 2);

    $b->delete();

    expect(fn () => $this->action->handle($a, $b))
        ->toThrow(InvalidArgumentException::class);
});

it('appends new assets at 1000, 2000, 3000 via AttachMediaAsset', function () {
    $listing = Listing::factory()->create();
    $attach = new \App\Actions\Media\AttachMediaAsset;

    $positions = [];
    for ($i = 0; $i < 3; $i++) {
        $positions[] = $attach->handle($listing, ['path' => "media/{$i}.jpg"])->sort_order;
    }

    expect($positions)->toBe([1000, 2000, 3000]);
});
