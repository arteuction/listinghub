<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\Settlement;
use App\Services\Catalog\PublicListingQuery;

/*
| 3.3.0 — public marketplace. The installer lock is created so EnsureInstalled
| lets requests through to the catalog instead of redirecting to the wizard.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function placeIn(string $regionSlug, string $settlementSlug): Settlement
{
    $region = Region::factory()->create(['slug' => $regionSlug]);
    $municipality = Municipality::factory()->create(['region_id' => $region->id]);

    return Settlement::factory()->create([
        'municipality_id' => $municipality->id,
        'slug' => $settlementSlug,
    ]);
}

it('renders the home page', function () {
    $this->get('/')->assertOk()->assertSee('Намерете фирми и услуги в България');
});

it('lists only published listings', function () {
    $published = Listing::factory()->published()->create(['title' => 'Публикувана обява']);
    $draft = Listing::factory()->create(['title' => 'Чернова обява']);

    $this->get(route('listings.index'))
        ->assertOk()
        ->assertSee($published->title)
        ->assertDontSee($draft->title);
});

it('excludes a published listing that has no published_at', function () {
    Listing::factory()->create([
        'title' => 'Без дата на публикуване',
        'status' => ListingStatus::Published->value,
        'published_at' => null,
    ]);

    $this->get(route('listings.index'))->assertOk()->assertDontSee('Без дата на публикуване');
});

it('filters by keyword across title and description', function () {
    Listing::factory()->published()->create(['title' => 'Пекарна Слънце']);
    Listing::factory()->published()->create(['title' => 'Автосервиз', 'description' => 'Ремонт на пекарни машини']);
    Listing::factory()->published()->create(['title' => 'Книжарница']);

    $res = $this->get(route('listings.index', ['q' => 'пекарн']))->assertOk();

    $res->assertSee('Пекарна Слънце')->assertSee('Автосервиз')->assertDontSee('Книжарница');
});

it('treats LIKE wildcards in the keyword as literal characters', function () {
    Listing::factory()->published()->create(['title' => 'Нормална обява']);

    // A bare % must not match everything.
    $this->get(route('listings.index', ['q' => '%']))
        ->assertOk()
        ->assertDontSee('Нормална обява');
});

it('filters by category including its descendants', function () {
    $parent = Category::factory()->create(['slug' => 'uslugi']);
    $child = Category::factory()->create(['parent_id' => $parent->id]);
    $other = Category::factory()->create();

    Listing::factory()->published()->create(['category_id' => $parent->id, 'title' => 'Пряка обява']);
    Listing::factory()->published()->create(['category_id' => $child->id, 'title' => 'Подкатегорийна обява']);
    Listing::factory()->published()->create(['category_id' => $other->id, 'title' => 'Чужда обява']);

    $this->get(route('categories.show', 'uslugi'))
        ->assertOk()
        ->assertSee('Пряка обява')
        ->assertSee('Подкатегорийна обява')
        ->assertDontSee('Чужда обява');
});

it('returns 404 for an inactive category page', function () {
    Category::factory()->create(['slug' => 'skrita', 'is_active' => false]);

    $this->get(route('categories.show', 'skrita'))->assertNotFound();
});

it('filters by region through the settlement hierarchy', function () {
    $sofia = placeIn('sofia-grad', 'sofia');
    $varna = placeIn('varna', 'varna-grad');

    Listing::factory()->published()->create(['settlement_id' => $sofia->id, 'title' => 'Софийска обява']);
    Listing::factory()->published()->create(['settlement_id' => $varna->id, 'title' => 'Варненска обява']);

    $this->get(route('regions.show', 'sofia-grad'))
        ->assertOk()
        ->assertSee('Софийска обява')
        ->assertDontSee('Варненска обява');
});

it('ignores a category query parameter on a canonical category page', function () {
    Category::factory()->create(['slug' => 'restoranti']);
    $other = Category::factory()->create(['slug' => 'hoteli']);
    Listing::factory()->published()->create(['category_id' => $other->id, 'title' => 'Хотелска обява']);

    // The bound route parameter must win over ?category=.
    $this->get(route('categories.show', 'restoranti').'?category=hoteli')
        ->assertOk()
        ->assertDontSee('Хотелска обява');
});

it('orders featured listings first regardless of sort', function () {
    Listing::factory()->published()->create(['title' => 'Обикновена', 'published_at' => now()]);
    Listing::factory()->published()->create([
        'title' => 'Промотирана',
        'is_featured' => true,
        'published_at' => now()->subYear(),
    ]);

    $html = $this->get(route('listings.index'))->assertOk()->getContent();

    expect(strpos($html, 'Промотирана'))->toBeLessThan(strpos($html, 'Обикновена'));
});

it('falls back to the default sort for an unknown sort key', function () {
    Listing::factory()->published()->create(['title' => 'Единствена обява']);

    $this->get(route('listings.index', ['sort' => 'nonsense; drop table']))
        ->assertOk()
        ->assertSee('Единствена обява');
});

it('paginates and preserves the query string', function () {
    Listing::factory()->published()->count(PublicListingQuery::PER_PAGE + 5)->create();

    $res = $this->get(route('listings.index', ['q' => '', 'sort' => 'title']))->assertOk();

    $res->assertSee('page=2', escape: false);
});

it('shows a published listing and counts the view', function () {
    $listing = Listing::factory()->published()->create(['title' => 'Детайлна обява', 'views' => 0]);

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertSee('Детайлна обява');

    expect((int) $listing->fresh()->views)->toBe(1);
});

it('hides a non-published listing behind a 404', function (string $status) {
    $listing = Listing::factory()->create(['status' => $status]);

    $this->get(route('listings.show', $listing->slug))->assertNotFound();
})->with([
    'draft' => [ListingStatus::Draft->value],
    'pending' => [ListingStatus::Pending->value],
    'suspended' => [ListingStatus::Suspended->value],
]);

it('does not count a view for a listing it refuses to show', function () {
    $listing = Listing::factory()->create(['views' => 0]);

    $this->get(route('listings.show', $listing->slug))->assertNotFound();

    expect((int) $listing->fresh()->views)->toBe(0);
});

it('serves a sitemap containing published listings only', function () {
    $published = Listing::factory()->published()->create();
    $draft = Listing::factory()->create();

    $res = $this->get('/sitemap.xml')->assertOk();

    expect($res->headers->get('Content-Type'))->toContain('application/xml');
    $res->assertSee(route('listings.show', $published->slug), escape: false)
        ->assertDontSee(route('listings.show', $draft->slug), escape: false);
});
