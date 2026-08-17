<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\Settlement;

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));

    $country = Country::factory()->create(['iso2' => 'BG', 'slug' => 'bulgaria']);
    $this->region = Region::factory()->create([
        'country_id' => $country->id,
        'name' => 'Пловдив',
        'slug' => 'plovdiv',
    ]);
    $municipality = Municipality::factory()->create([
        'region_id' => $this->region->id,
        'name' => 'Пловдив',
    ]);
    $this->settlement = Settlement::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Пловдив',
        'slug' => 'plovdiv-city',
        'type' => 'city',
        'latitude' => 42.15,
        'longitude' => 24.75,
    ]);
    Settlement::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Асеновград',
        'slug' => 'asenovgrad',
        'type' => 'city',
        'latitude' => 42.01,
        'longitude' => 24.87,
    ]);
});

// ─── Settlement Lookup (cascading picker) ───────────────────────────────────

it('returns settlements for a region', function () {
    $this->getJson(route('regions.settlements', $this->region))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment(['name' => 'Пловдив'])
        ->assertJsonFragment(['name' => 'Асеновград']);
});

it('returns an empty array for a region with no settlements', function () {
    $country = Country::factory()->create(['iso2' => 'XX', 'slug' => 'test']);
    $emptyRegion = Region::factory()->create([
        'country_id' => $country->id,
        'name' => 'Empty',
        'slug' => 'empty-region',
    ]);

    $this->getJson(route('regions.settlements', $emptyRegion))
        ->assertOk()
        ->assertJsonCount(0);
});

it('sets cache-control header on settlement lookup', function () {
    $response = $this->getJson(route('regions.settlements', $this->region));

    expect($response->headers->get('Cache-Control'))->toContain('max-age=3600');
});

it('returns 404 for a non-existent region slug', function () {
    $this->getJson('/regions/nonexistent/settlements')
        ->assertNotFound();
});

// ─── Settlement Search (autocomplete) ───────────────────────────────────────

it('searches settlements by name', function () {
    $this->getJson('/api/catalog/settlements?q=Пловдив')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Пловдив'])
        ->assertJsonFragment(['region' => 'Пловдив']);
});

it('returns empty for a query shorter than 2 characters', function () {
    $this->getJson('/api/catalog/settlements?q=П')
        ->assertOk()
        ->assertJsonCount(0);
});

it('returns empty for an empty query', function () {
    $this->getJson('/api/catalog/settlements?q=')
        ->assertOk()
        ->assertJsonCount(0);
});

it('returns empty when no settlements match', function () {
    $this->getJson('/api/catalog/settlements?q=Несъществуващо')
        ->assertOk()
        ->assertJsonCount(0);
});

it('includes municipality and region in search results', function () {
    $response = $this->getJson('/api/catalog/settlements?q=Асеновград')
        ->assertOk()
        ->assertJsonCount(1);

    $item = $response->json()[0];
    expect($item)->toHaveKeys(['id', 'name', 'slug', 'type', 'municipality', 'region', 'region_slug']);
});
