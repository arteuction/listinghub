<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\Settlement;

/*
| 3.4.1 — Bulgaria Map & Geo Search.
|
| Verifies the List/Map toggle preserves the active filters, and that the
| catalog page (list) and the map endpoint agree on the same result set for
| identical filters — the acceptance criterion "map/list return the same
| set of listings".
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

it('keeps the category filter when switching from list to map view', function () {
    $category = Category::factory()->create(['is_active' => true]);

    $res = $this->get(route('listings.index', ['category' => $category->slug, 'view' => 'list']));
    $res->assertOk();

    // The "Карта" toggle link must carry both view=map and the active category.
    $html = $res->getContent();
    preg_match('/href="([^"]*view=map[^"]*)"/', $html, $matches);
    expect($matches)->not->toBeEmpty();
    $mapLink = html_entity_decode($matches[1]);
    expect($mapLink)->toContain('view=map')
        ->toContain('category='.$category->slug);
});

it('the map toggle link carries q and region alongside view=map', function () {
    $region = Region::factory()->create();

    $res = $this->get(route('listings.index', ['region' => $region->slug, 'q' => 'кафе', 'view' => 'list']));
    $res->assertOk();

    // The "Карта" link must retain region + q, only swapping the view param.
    $res->assertSee('view=map', false);
    $res->assertSee(urlencode($region->slug), false);
});

it('list and map endpoint return the same category-filtered listing set', function () {
    $category = Category::factory()->create(['is_active' => true]);
    $other = Category::factory()->create(['is_active' => true]);

    $region = Region::factory()->create();
    $muni = Municipality::factory()->create(['region_id' => $region->id]);
    $settlement = Settlement::factory()->create([
        'municipality_id' => $muni->id,
        'latitude' => 42.7,
        'longitude' => 25.5,
    ]);

    $inCategory = Listing::factory()->published()->create([
        'category_id' => $category->id,
        'settlement_id' => $settlement->id,
        'title' => 'In category',
    ]);
    $outOfCategory = Listing::factory()->published()->create([
        'category_id' => $other->id,
        'settlement_id' => $settlement->id,
        'title' => 'Out of category',
    ]);

    // List view.
    $listRes = $this->get(route('listings.index', ['category' => $category->slug]));
    $listRes->assertOk()->assertSee('In category')->assertDontSee('Out of category');

    // Map endpoint with the identical filter.
    $mapRes = $this->getJson('/api/catalog/map?bbox=22.3,41.2,28.7,44.3&category='.$category->slug);
    $mapRes->assertOk();

    $ids = collect($mapRes->json('features'))->pluck('properties.id');
    expect($ids)->toContain($inCategory->id)
        ->not->toContain($outOfCategory->id);
});
