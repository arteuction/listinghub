<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\Settlement;

/*
| 3.5.0 — Bulgaria Map & Geo Search. Tests for GET /api/catalog/map.
|
| Key invariants:
|   • Only Published listings appear.
|   • Filter parity: map and catalog agree on category, region, settlement.
|   • bbox outside Bulgaria returns 422.
|   • Listings with no resolvable coordinates are excluded.
|   • Settlement centroid is used when the listing has no explicit lat/lng.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

// Bulgaria bounding box (whole country).
const BG_BBOX = '22.3,41.2,28.7,44.3';

// ─── helpers ────────────────────────────────────────────────────────────────

function settlementAt(float $lat, float $lng): Settlement
{
    $region = Region::factory()->create();
    $muni = Municipality::factory()->create(['region_id' => $region->id]);

    return Settlement::factory()->create([
        'municipality_id' => $muni->id,
        'latitude' => $lat,
        'longitude' => $lng,
    ]);
}

function publishedAt(Settlement $s, ?float $lat = null, ?float $lng = null): Listing
{
    return Listing::factory()->published()->create([
        'settlement_id' => $s->id,
        'latitude' => $lat,
        'longitude' => $lng,
    ]);
}

// ─── tests ──────────────────────────────────────────────────────────────────

it('returns 422 when bbox is missing', function () {
    $this->getJson('/api/catalog/map')->assertStatus(422);
});

it('returns 422 for a bbox outside Bulgaria', function () {
    // Paris
    $this->getJson('/api/catalog/map?bbox=2.2,48.8,2.4,49.0')->assertStatus(422);
});

it('returns 422 for a malformed bbox', function () {
    $this->getJson('/api/catalog/map?bbox=not,a,valid')->assertStatus(422);
});

it('returns a GeoJSON FeatureCollection for a valid bbox', function () {
    $res = $this->get('/api/catalog/map?bbox='.BG_BBOX);

    $res->assertOk()
        ->assertJson(['type' => 'FeatureCollection'])
        ->assertJsonStructure(['type', 'features']);
});

it('excludes draft and pending listings', function () {
    $s = settlementAt(42.7, 25.5);
    Listing::factory()->create(['settlement_id' => $s->id, 'title' => 'Draft one']);
    Listing::factory()->create([
        'settlement_id' => $s->id,
        'title' => 'Pending one',
        'status' => ListingStatus::Pending->value,
    ]);

    $res = $this->getJson('/api/catalog/map?bbox='.BG_BBOX);
    $res->assertOk();

    $titles = collect($res->json('features'))->pluck('properties.title');
    expect($titles)->not->toContain('Draft one')
        ->not->toContain('Pending one');
});

it('excludes suspended listings', function () {
    $s = settlementAt(42.7, 25.5);
    Listing::factory()->create([
        'settlement_id' => $s->id,
        'title' => 'Suspended one',
        'status' => ListingStatus::Suspended->value,
        'published_at' => now(),
    ]);

    $res = $this->getJson('/api/catalog/map?bbox='.BG_BBOX);
    $res->assertOk();

    $titles = collect($res->json('features'))->pluck('properties.title');
    expect($titles)->not->toContain('Suspended one');
});

it('includes published listings within the bbox', function () {
    $s = settlementAt(42.7, 25.5); // inside Bulgaria
    publishedAt($s);

    $res = $this->getJson('/api/catalog/map?bbox='.BG_BBOX);
    $res->assertOk();

    expect($res->json('features'))->not->toBeEmpty();
});

it('uses the settlement centroid when the listing has no explicit coordinates', function () {
    $s = settlementAt(42.5, 25.0);
    $listing = publishedAt($s, lat: null, lng: null);

    $res = $this->getJson('/api/catalog/map?bbox='.BG_BBOX);
    $res->assertOk();

    $feature = collect($res->json('features'))
        ->firstWhere('properties.id', $listing->id);

    expect($feature)->not->toBeNull()
        ->and((float) $feature['geometry']['coordinates'][0])->toBe(25.0)
        ->and((float) $feature['geometry']['coordinates'][1])->toBe(42.5)
        ->and($feature['properties']['exact'])->toBeFalse();
});

it('uses the listing explicit coordinates when set', function () {
    $s = settlementAt(42.5, 25.0);  // settlement centroid
    $listing = publishedAt($s, lat: 42.6001, lng: 25.1001); // precise point

    $res = $this->getJson('/api/catalog/map?bbox='.BG_BBOX);
    $res->assertOk();

    $feature = collect($res->json('features'))
        ->firstWhere('properties.id', $listing->id);

    expect($feature)->not->toBeNull()
        ->and($feature['geometry']['coordinates'][0])->toBeGreaterThan(25.1)
        ->and($feature['properties']['exact'])->toBeTrue();
});

it('excludes listings with no resolvable coordinates', function () {
    // Settlement with null lat/lng, listing also with null → no coords at all.
    $region = Region::factory()->create();
    $muni = Municipality::factory()->create(['region_id' => $region->id]);
    $s = Settlement::factory()->create([
        'municipality_id' => $muni->id,
        'latitude' => null,
        'longitude' => null,
    ]);
    $listing = publishedAt($s, lat: null, lng: null);

    $res = $this->getJson('/api/catalog/map?bbox='.BG_BBOX);
    $res->assertOk();

    $ids = collect($res->json('features'))->pluck('properties.id');
    expect($ids)->not->toContain($listing->id);
});

it('filters by category — map and catalog agree', function () {
    $catA = Category::factory()->create(['is_active' => true]);
    $catB = Category::factory()->create(['is_active' => true]);

    $s = settlementAt(42.7, 25.5);

    $inA = Listing::factory()->published()->create(['settlement_id' => $s->id, 'category_id' => $catA->id, 'title' => 'In A']);
    $inB = Listing::factory()->published()->create(['settlement_id' => $s->id, 'category_id' => $catB->id, 'title' => 'In B']);

    $res = $this->getJson('/api/catalog/map?bbox='.BG_BBOX.'&category='.$catA->slug);
    $res->assertOk();

    $ids = collect($res->json('features'))->pluck('properties.id');
    expect($ids)->toContain($inA->id)
        ->not->toContain($inB->id);
});

it('filters by region', function () {
    $regionA = Region::factory()->create();
    $regionB = Region::factory()->create();
    $muniA = Municipality::factory()->create(['region_id' => $regionA->id]);
    $muniB = Municipality::factory()->create(['region_id' => $regionB->id]);
    $sA = Settlement::factory()->create(['municipality_id' => $muniA->id, 'latitude' => 42.7, 'longitude' => 25.5]);
    $sB = Settlement::factory()->create(['municipality_id' => $muniB->id, 'latitude' => 43.1, 'longitude' => 26.0]);

    $listingA = publishedAt($sA);
    $listingB = publishedAt($sB);

    $res = $this->getJson('/api/catalog/map?bbox='.BG_BBOX.'&region='.$regionA->slug);
    $res->assertOk();

    $ids = collect($res->json('features'))->pluck('properties.id');
    expect($ids)->toContain($listingA->id)
        ->not->toContain($listingB->id);
});

it('feature properties contain url, title, settlement and exact flag', function () {
    $s = settlementAt(42.7, 25.5);
    $listing = publishedAt($s, lat: 42.71, lng: 25.51);

    $res = $this->getJson('/api/catalog/map?bbox='.BG_BBOX);
    $res->assertOk();

    $feature = collect($res->json('features'))->firstWhere('properties.id', $listing->id);
    expect($feature)->not->toBeNull()
        ->and($feature['properties'])->toHaveKeys(['id', 'title', 'url', 'settlement', 'exact'])
        ->and($feature['properties']['url'])->toContain($listing->slug);
});
