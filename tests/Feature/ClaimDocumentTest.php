<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingClaim;
use App\Models\User;
use App\Services\Claims\ClaimDocumentProcessor;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| 3.5.2 — Verified claim documents.
|
| Key invariants:
|   • Type is decided by CONTENT, never extension: an executable renamed
|     .pdf is rejected; a real PDF/JPEG/PNG is accepted.
|   • Images are re-encoded (only pixels survive); PDFs are stored raw on a
|     PRIVATE disk with no URL, downloadable only by staff, as attachment,
|     with X-Content-Type-Options: nosniff.
|   • Stored names are random UUIDs; the client filename is metadata only.
|   • SHA-256, MIME, size and original name are recorded alongside the path.
|   • Deleting a claim deletes its file.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);
    Storage::fake(ClaimDocumentProcessor::DISK);
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function docClaimant(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('member');

    return $user;
}

function docAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

function claimableListing(): Listing
{
    return Listing::factory()->published()->create();
}

function fakePdf(string $name = 'proof.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
}

// ------------------------------------------------------------------- uploads

it('accepts a real PDF and stores it privately with full metadata', function () {
    $user = docClaimant();
    $listing = claimableListing();

    $this->actingAs($user)->post(route('listings.claims.store', $listing->slug), [
        'message' => 'Аз съм собственикът.',
        'document' => fakePdf('доказателство.pdf'),
    ])->assertSessionHasNoErrors();

    $claim = ListingClaim::query()->firstOrFail();

    expect($claim->document_disk)->toBe(ClaimDocumentProcessor::DISK)
        ->and($claim->document_mime)->toBe('application/pdf')
        ->and($claim->document_original_name)->toBe('доказателство.pdf')
        ->and((int) $claim->document_size)->toBeGreaterThan(0)
        ->and(strlen((string) $claim->document_sha256))->toBe(64)
        // Random UUID name with OUR extension — never the client filename.
        ->and($claim->document_path)->toMatch('#^claim-documents/[0-9a-f-]{36}\.pdf$#');

    Storage::disk(ClaimDocumentProcessor::DISK)->assertExists((string) $claim->document_path);

    $stored = (string) Storage::disk(ClaimDocumentProcessor::DISK)->get((string) $claim->document_path);
    expect(hash('sha256', $stored))->toBe($claim->document_sha256);
});

it('re-encodes an uploaded image so only pixels survive', function () {
    $user = docClaimant();
    $listing = claimableListing();

    // A real PNG with a payload appended after IEND — the polyglot shape.
    $png = UploadedFile::fake()->image('proof.png', 50, 50);
    $bytes = file_get_contents($png->getRealPath())."<?php system('id');";
    $upload = UploadedFile::fake()->createWithContent('proof.png', $bytes);

    $this->actingAs($user)->post(route('listings.claims.store', $listing->slug), [
        'message' => 'Аз съм собственикът.',
        'document' => $upload,
    ])->assertSessionHasNoErrors();

    $claim = ListingClaim::query()->firstOrFail();

    expect($claim->document_mime)->toBe('image/webp')
        ->and($claim->document_path)->toEndWith('.webp');

    $stored = (string) Storage::disk(ClaimDocumentProcessor::DISK)->get((string) $claim->document_path);
    expect($stored)->not->toContain('<?php');
});

it('rejects an executable masked as a PDF', function () {
    $user = docClaimant();
    $listing = claimableListing();

    $upload = UploadedFile::fake()->createWithContent('invoice.pdf', "MZ\x90\x00<?php system('id');");

    $this->actingAs($user)->post(route('listings.claims.store', $listing->slug), [
        'message' => 'Аз съм собственикът.',
        'document' => $upload,
    ])->assertSessionHasErrors('document');

    expect(ListingClaim::query()->count())->toBe(0)
        ->and(Storage::disk(ClaimDocumentProcessor::DISK)->allFiles())->toBe([]);
});

it('rejects an oversized document', function () {
    $user = docClaimant();
    $listing = claimableListing();

    // Over the 10 MB validation cap (10240 KB).
    $upload = UploadedFile::fake()->create('big.pdf', 11 * 1024);

    $this->actingAs($user)->post(route('listings.claims.store', $listing->slug), [
        'message' => 'Аз съм собственикът.',
        'document' => $upload,
    ])->assertSessionHasErrors('document');
});

it('still accepts a claim without any document', function () {
    $user = docClaimant();
    $listing = claimableListing();

    $this->actingAs($user)->post(route('listings.claims.store', $listing->slug), [
        'message' => 'Аз съм собственикът.',
    ])->assertSessionHasNoErrors();

    expect(ListingClaim::query()->firstOrFail()->hasDocument())->toBeFalse();
});

// ------------------------------------------------------------------ download

it('lets an admin download the document as an attachment with nosniff', function () {
    $user = docClaimant();
    $listing = claimableListing();

    $this->actingAs($user)->post(route('listings.claims.store', $listing->slug), [
        'message' => 'Аз съм собственикът.',
        'document' => fakePdf(),
    ]);

    $claim = ListingClaim::query()->firstOrFail();

    $response = $this->actingAs(docAdmin())->get(route('admin.claims.document', $claim));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Type', 'application/pdf');

    expect((string) $response->headers->get('Content-Disposition'))->toContain('attachment');
});

it('blocks a member — including the claimant — from downloading', function () {
    $user = docClaimant();
    $listing = claimableListing();

    $this->actingAs($user)->post(route('listings.claims.store', $listing->slug), [
        'message' => 'Аз съм собственикът.',
        'document' => fakePdf(),
    ]);

    $claim = ListingClaim::query()->firstOrFail();

    $this->actingAs($user)->get(route('admin.claims.document', $claim))->assertForbidden();

    auth()->logout();
    $this->get(route('admin.claims.document', $claim))->assertRedirect(); // guest → login
});

it('returns 404 for a claim without a document', function () {
    $user = docClaimant();
    $listing = claimableListing();

    $this->actingAs($user)->post(route('listings.claims.store', $listing->slug), [
        'message' => 'Аз съм собственикът.',
    ]);

    $claim = ListingClaim::query()->firstOrFail();

    $this->actingAs(docAdmin())->get(route('admin.claims.document', $claim))->assertNotFound();
});

// ------------------------------------------------------------------- cleanup

it('deletes the stored file when the claim is deleted', function () {
    $user = docClaimant();
    $listing = claimableListing();

    $this->actingAs($user)->post(route('listings.claims.store', $listing->slug), [
        'message' => 'Аз съм собственикът.',
        'document' => fakePdf(),
    ]);

    $claim = ListingClaim::query()->firstOrFail();
    $path = (string) $claim->document_path;
    Storage::disk(ClaimDocumentProcessor::DISK)->assertExists($path);

    $claim->delete();

    Storage::disk(ClaimDocumentProcessor::DISK)->assertMissing($path);
});
