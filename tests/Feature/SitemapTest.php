<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\Settlement;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
});

it('returns valid XML with content-type application/xml', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    expect($response->getContent())->toContain('<?xml')
        ->and($response->getContent())->toContain('<urlset');
});

it('includes the home page with priority 1.0', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSeeText(route('home'))
        ->assertSeeText('1.0');
});

it('includes active categories', function () {
    $category = Category::factory()->create(['is_active' => true, 'slug' => 'restaurants']);

    $response = $this->get('/sitemap.xml');

    expect($response->getContent())->toContain('/restaurants');
});

it('excludes inactive categories', function () {
    Category::factory()->create(['is_active' => false, 'slug' => 'hidden-cat']);

    $response = $this->get('/sitemap.xml');

    expect($response->getContent())->not->toContain('/hidden-cat');
});

it('includes regions', function () {
    $country = Country::factory()->create(['iso2' => 'BG', 'slug' => 'bg']);
    Region::factory()->create(['country_id' => $country->id, 'slug' => 'sofia-region']);

    $response = $this->get('/sitemap.xml');

    expect($response->getContent())->toContain('/sofia-region');
});

it('includes published listings', function () {
    $this->seed(RoleSeeder::class);
    $category = Category::factory()->create();
    $user = User::factory()->create();
    $country = Country::factory()->create();
    $region = Region::factory()->create(['country_id' => $country->id]);
    $municipality = Municipality::factory()->create(['region_id' => $region->id]);
    $settlement = Settlement::factory()->create(['municipality_id' => $municipality->id]);

    $listing = Listing::factory()->create([
        'category_id' => $category->id,
        'user_id' => $user->id,
        'settlement_id' => $settlement->id,
        'status' => ListingStatus::Published,
        'published_at' => now(),
        'slug' => 'my-restaurant',
    ]);

    $response = $this->get('/sitemap.xml');

    expect($response->getContent())->toContain('/my-restaurant');
});

it('excludes draft listings', function () {
    $this->seed(RoleSeeder::class);
    $category = Category::factory()->create();
    $user = User::factory()->create();
    $country = Country::factory()->create();
    $region = Region::factory()->create(['country_id' => $country->id]);
    $municipality = Municipality::factory()->create(['region_id' => $region->id]);
    $settlement = Settlement::factory()->create(['municipality_id' => $municipality->id]);

    Listing::factory()->create([
        'category_id' => $category->id,
        'user_id' => $user->id,
        'settlement_id' => $settlement->id,
        'status' => ListingStatus::Draft,
        'slug' => 'draft-listing',
    ]);

    $response = $this->get('/sitemap.xml');

    expect($response->getContent())->not->toContain('/draft-listing');
});
