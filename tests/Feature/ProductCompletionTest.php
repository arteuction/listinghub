<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Listing;
use App\Models\Product;
use App\Models\User;
use App\Services\Media\ImageProcessor;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| 3.5.3 — Product completion: attributes, gallery, public detail.
|
| Key invariants:
|   • Attribute sync is a FULL REPLACEMENT, ordered as written; attribute
|     definitions are global (shared by slug), values are per-product.
|   • Public detail shows ONLY a Published product on a Published listing;
|     everything else — draft/suspended product, unpublished listing, a slug
|     from a different listing — is indistinguishable from nonexistent.
|   • The product gallery runs through the SAME verified media pipeline as
|     the listing gallery, and every id is checked against its parent.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function pcOwner(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('member');

    return $user;
}

function pcListing(User $owner): Listing
{
    return Listing::factory()->published()->create(['user_id' => $owner->id]);
}

// ---------------------------------------------------------------- attributes

it('stores attributes in the order they were written', function () {
    $owner = pcOwner();
    $listing = pcListing($owner);

    $this->actingAs($owner)->post(route('member.listings.products.store', $listing), [
        'name' => 'Пакет Стандарт',
        'price_minor' => 1000,
        'attributes' => [
            ['name' => 'Гаранция', 'value' => '2 години'],
            ['name' => 'Цвят', 'value' => 'Червен'],
            ['name' => 'Тегло', 'value' => '1.2 кг'],
        ],
    ])->assertSessionHasNoErrors();

    $product = Product::query()->firstOrFail();
    $rows = $product->attributeValues()->with('attribute')->get();

    expect($rows->pluck('attribute.name')->all())->toBe(['Гаранция', 'Цвят', 'Тегло'])
        ->and($rows->pluck('value')->all())->toBe(['2 години', 'Червен', '1.2 кг'])
        ->and($rows->pluck('sort_order')->all())->toBe([0, 1, 2]);
});

it('replaces attributes fully on update — a removed row does not survive', function () {
    $owner = pcOwner();
    $listing = pcListing($owner);
    $product = Product::factory()->for($listing)->create();

    $attribute = Attribute::query()->create(['name' => 'Гаранция', 'slug' => 'garantsiya']);
    AttributeValue::query()->create([
        'attribute_id' => $attribute->id, 'product_id' => $product->id, 'value' => '2 години', 'sort_order' => 0,
    ]);

    $this->actingAs($owner)->put(route('member.listings.products.update', [$listing, $product]), [
        'name' => $product->name,
        'price_minor' => $product->price_minor,
        'attributes' => [
            ['name' => 'Цвят', 'value' => 'Син'],
        ],
    ])->assertSessionHasNoErrors();

    $rows = $product->attributeValues()->with('attribute')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->attribute->name)->toBe('Цвят');
});

it('shares attribute definitions across products by slug', function () {
    $owner = pcOwner();
    $listing = pcListing($owner);

    foreach (['Продукт А', 'Продукт Б'] as $name) {
        $this->actingAs($owner)->post(route('member.listings.products.store', $listing), [
            'name' => $name,
            'price_minor' => 100,
            'attributes' => [['name' => 'Цвят', 'value' => 'Червен']],
        ]);
    }

    expect(Attribute::query()->where('slug', 'cviat')->count())->toBe(1)
        ->and(AttributeValue::query()->count())->toBe(2);
});

it('keeps the last value when a name is duplicated in one payload', function () {
    $owner = pcOwner();
    $listing = pcListing($owner);

    $this->actingAs($owner)->post(route('member.listings.products.store', $listing), [
        'name' => 'Пакет',
        'price_minor' => 100,
        'attributes' => [
            ['name' => 'Цвят', 'value' => 'Червен'],
            ['name' => 'Цвят', 'value' => 'Син'],
        ],
    ])->assertSessionHasNoErrors();

    $product = Product::query()->firstOrFail();

    expect($product->attributeValues)->toHaveCount(1)
        ->and($product->attributeValues->first()->value)->toBe('Син');
});

// ------------------------------------------------------------- public detail

it('shows a published product on a published listing with ordered attributes', function () {
    $owner = pcOwner();
    $listing = pcListing($owner);
    $product = Product::factory()->for($listing)->create([
        'status' => 'published', 'name' => 'Пакет Про', 'price_minor' => 2500, 'currency' => 'BGN',
    ]);

    $attr = Attribute::query()->create(['name' => 'Гаранция', 'slug' => 'garantsiya']);
    AttributeValue::query()->create([
        'attribute_id' => $attr->id, 'product_id' => $product->id, 'value' => '2 години', 'sort_order' => 0,
    ]);

    $this->get(route('listings.products.show', [$listing->slug, $product->slug]))
        ->assertOk()
        ->assertSee('Пакет Про')
        ->assertSee('Гаранция')
        ->assertSee('2 години')
        ->assertSee($listing->title);
});

it('hides a draft or suspended product from the public detail page', function (string $status) {
    $owner = pcOwner();
    $listing = pcListing($owner);
    $product = Product::factory()->for($listing)->create(['status' => $status]);

    $this->get(route('listings.products.show', [$listing->slug, $product->slug]))->assertNotFound();
})->with(['draft', 'suspended']);

it('hides a published product whose listing is not published', function () {
    $owner = pcOwner();
    $listing = Listing::factory()->create(['user_id' => $owner->id, 'status' => 'pending']);
    $product = Product::factory()->for($listing)->create(['status' => 'published']);

    $this->get(route('listings.products.show', [$listing->slug, $product->slug]))->assertNotFound();
});

it('404s a product slug that belongs to a different listing', function () {
    $owner = pcOwner();
    $listingA = pcListing($owner);
    $listingB = pcListing($owner);
    $productB = Product::factory()->for($listingB)->create(['status' => 'published']);

    $this->get(route('listings.products.show', [$listingA->slug, $productB->slug]))->assertNotFound();
});

it('links each published product from the listing page to its detail page', function () {
    $owner = pcOwner();
    $listing = pcListing($owner);
    $product = Product::factory()->for($listing)->create(['status' => 'published']);

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertSee(route('listings.products.show', [$listing->slug, $product->slug]), false);
});

// ------------------------------------------------------------------- gallery

it('lets the owner upload a product image through the verified pipeline', function () {
    Storage::fake(ImageProcessor::DISK);
    $owner = pcOwner();
    $listing = pcListing($owner);
    $product = Product::factory()->for($listing)->create();

    $this->actingAs($owner)->post(route('member.listings.products.media.store', [$listing, $product]), [
        'images' => [UploadedFile::fake()->image('photo.jpg', 100, 100)],
    ])->assertSessionHasNoErrors();

    $asset = $product->media()->firstOrFail();

    // Re-encoded to WebP under a generated name — never the client filename.
    expect($asset->path)->toEndWith('.webp')
        ->and($asset->mime_type)->toBe('image/webp');

    Storage::disk(ImageProcessor::DISK)->assertExists($asset->path);
});

it('blocks a stranger from managing another member\'s product gallery', function () {
    Storage::fake(ImageProcessor::DISK);
    $owner = pcOwner();
    $stranger = pcOwner();
    $listing = pcListing($owner);
    $product = Product::factory()->for($listing)->create();

    $this->actingAs($stranger)->post(route('member.listings.products.media.store', [$listing, $product]), [
        'images' => [UploadedFile::fake()->image('photo.jpg', 100, 100)],
    ])->assertForbidden();
});

it('404s a product id from a different listing on the media routes', function () {
    Storage::fake(ImageProcessor::DISK);
    $owner = pcOwner();
    $listingA = pcListing($owner);
    $listingB = pcListing($owner);
    $productB = Product::factory()->for($listingB)->create();

    $this->actingAs($owner)->post(route('member.listings.products.media.store', [$listingA, $productB]), [
        'images' => [UploadedFile::fake()->image('photo.jpg', 100, 100)],
    ])->assertNotFound();
});

it('deletes a product image and its file', function () {
    Storage::fake(ImageProcessor::DISK);
    $owner = pcOwner();
    $listing = pcListing($owner);
    $product = Product::factory()->for($listing)->create();

    $this->actingAs($owner)->post(route('member.listings.products.media.store', [$listing, $product]), [
        'images' => [UploadedFile::fake()->image('photo.jpg', 100, 100)],
    ]);

    $asset = $product->media()->firstOrFail();
    $path = $asset->path;

    $this->actingAs($owner)
        ->delete(route('member.listings.products.media.destroy', [$listing, $product, $asset]))
        ->assertSessionHasNoErrors();

    expect($product->media()->count())->toBe(0);
    Storage::disk(ImageProcessor::DISK)->assertMissing($path);
});

it('404s an asset that belongs to a different product', function () {
    Storage::fake(ImageProcessor::DISK);
    $owner = pcOwner();
    $listing = pcListing($owner);
    $productA = Product::factory()->for($listing)->create();
    $productB = Product::factory()->for($listing)->create();

    $this->actingAs($owner)->post(route('member.listings.products.media.store', [$listing, $productB]), [
        'images' => [UploadedFile::fake()->image('photo.jpg', 100, 100)],
    ]);

    $asset = $productB->media()->firstOrFail();

    $this->actingAs($owner)
        ->delete(route('member.listings.products.media.destroy', [$listing, $productA, $asset]))
        ->assertNotFound();
});
