<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\ImageLimits;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| 3.4.0 — upload security.
|
| The point of these tests is the negative space: what must NOT end up on disk,
| and what must not survive re-encoding. A passing happy path proves very
| little on its own here.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);
    Storage::fake(App\Services\Media\ImageProcessor::DISK);
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function mediaOwner(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('member');

    return $user;
}

/** Real PNG bytes, generated rather than faked, so GD actually has to decode them. */
function pngBytes(int $width = 40, int $height = 30): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 10, 120, 200));

    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

function uploaded(string $bytes, string $name): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, null, null, true);
}

// ------------------------------------------------------------------- storage

it('stores an upload re-encoded as webp under a generated name', function () {
    $user = mediaOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('member.listings.media.store', $listing), [
        'images' => [uploaded(pngBytes(), 'снимка.png')],
    ])->assertRedirect();

    $asset = MediaAsset::query()->firstOrFail();

    expect($asset->mime_type)->toBe('image/webp')
        // The stored name is a UUID we generated; the client's name is kept
        // only as a display label.
        ->and($asset->path)->toMatch('#^listings/'.$listing->id.'/[0-9a-f-]{36}\.webp$#')
        ->and($asset->file_name)->toBe('снимка.png');

    Storage::disk('public')->assertExists($asset->path);

    // What landed on disk really is a WebP, not the PNG we sent.
    $stored = Storage::disk('public')->get($asset->path);
    expect(getimagesizefromstring($stored)[2])->toBe(IMAGETYPE_WEBP);
});

it('strips a payload appended to a genuine image', function () {
    $user = mediaOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    // A polyglot: valid PNG header and pixels, PHP glued on the end. This is
    // exactly the file that passes an extension check AND a content-type
    // sniff, which is why re-encoding — not validation — is what defeats it.
    $payload = "<?php system(\$_GET['c']); ?>";

    $this->actingAs($user)->post(route('member.listings.media.store', $listing), [
        'images' => [uploaded(pngBytes()."\n".$payload, 'polyglot.png')],
    ])->assertRedirect();

    $asset = MediaAsset::query()->firstOrFail();
    $stored = Storage::disk('public')->get($asset->path);

    expect($stored)->not->toContain('system')
        ->and($stored)->not->toContain('<?php')
        ->and(getimagesizefromstring($stored)[2])->toBe(IMAGETYPE_WEBP);
});

it('never lets the client filename reach the stored path', function () {
    $user = mediaOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('member.listings.media.store', $listing), [
        'images' => [uploaded(pngBytes(), '../../../../etc/passwd.png')],
    ])->assertRedirect();

    $asset = MediaAsset::query()->firstOrFail();

    expect($asset->path)->not->toContain('..')
        ->and($asset->path)->not->toContain('passwd')
        ->and($asset->path)->toStartWith('listings/'.$listing->id.'/');
});

it('downscales an image beyond the maximum dimension', function () {
    $user = mediaOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);
    $oversized = ImageLimits::MAX_DIMENSION + 400;

    $this->actingAs($user)->post(route('member.listings.media.store', $listing), [
        'images' => [uploaded(pngBytes($oversized, 200), 'big.png')],
    ])->assertRedirect();

    $asset = MediaAsset::query()->firstOrFail();

    expect($asset->metadata['width'])->toBe(ImageLimits::MAX_DIMENSION);
});

// ------------------------------------------------------------------ rejection

it('rejects a file that is not an image at all', function (string $bytes, string $name) {
    $user = mediaOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->from(route('member.listings.edit', $listing))
        ->post(route('member.listings.media.store', $listing), [
            'images' => [uploaded($bytes, $name)],
        ])->assertSessionHasErrors();

    expect(MediaAsset::query()->count())->toBe(0);
})->with([
    'php script' => ["<?php system(\$_GET['c']); ?>", 'shell.php.png'],
    'svg with script' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'x.svg'],
    'html' => ['<html><body>hi</body></html>', 'page.png'],
    'empty' => ['', 'empty.png'],
]);

it('enforces the per-listing gallery cap', function () {
    $user = mediaOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    MediaAsset::factory()->count(ImageLimits::MAX_PER_LISTING)->create([
        'mediable_type' => Listing::class,
        'mediable_id' => $listing->id,
        'collection' => 'gallery',
    ]);

    $this->actingAs($user)->from(route('member.listings.edit', $listing))
        ->post(route('member.listings.media.store', $listing), [
            'images' => [uploaded(pngBytes(), 'one-too-many.png')],
        ])->assertSessionHasErrors('images');

    expect(MediaAsset::query()->count())->toBe(ImageLimits::MAX_PER_LISTING);
});

// ------------------------------------------------------------------ ownership

it('refuses gallery writes on a listing owned by someone else', function () {
    $mine = mediaOwner();
    $theirs = Listing::factory()->create(['user_id' => mediaOwner()->id]);

    $this->actingAs($mine)->post(route('member.listings.media.store', $theirs), [
        'images' => [uploaded(pngBytes(), 'x.png')],
    ])->assertForbidden();

    expect(MediaAsset::query()->count())->toBe(0);
});

it('refuses to touch an asset belonging to a different listing', function (string $method, string $route) {
    $user = mediaOwner();
    $mine = Listing::factory()->create(['user_id' => $user->id]);
    $other = Listing::factory()->create(['user_id' => $user->id]);

    // Owned by the same member, but attached to the OTHER listing.
    $asset = MediaAsset::factory()->create([
        'mediable_type' => Listing::class,
        'mediable_id' => $other->id,
    ]);

    $this->actingAs($user)->call($method, route($route, [$mine, $asset]))->assertNotFound();

    expect(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue();
})->with([
    'delete' => ['DELETE', 'member.listings.media.destroy'],
    'cover' => ['POST', 'member.listings.media.cover'],
]);

it('deletes the row and the file together', function () {
    $user = mediaOwner();
    $listing = Listing::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('member.listings.media.store', $listing), [
        'images' => [uploaded(pngBytes(), 'gone.png')],
    ]);

    $asset = MediaAsset::query()->firstOrFail();
    $path = $asset->path;

    $this->actingAs($user)
        ->delete(route('member.listings.media.destroy', [$listing, $asset]))
        ->assertRedirect();

    expect(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($path);
});
