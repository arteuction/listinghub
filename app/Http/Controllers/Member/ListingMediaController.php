<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Actions\Media\AttachMediaAsset;
use App\Actions\Media\RepositionMediaAsset;
use App\Exceptions\InvalidImageUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\ListingMediaRequest;
use App\Models\Listing;
use App\Models\MediaAsset;
use App\Services\Media\ImageProcessor;
use App\Support\ImageLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gallery management for a listing the member owns.
 *
 * Every action re-checks ownership through ListingPolicy AND, for a specific
 * asset, that the asset actually belongs to that listing. Checking only the
 * listing would let an owner pass someone else's asset id and act on it.
 */
class ListingMediaController extends Controller
{
    public function store(ListingMediaRequest $request, Listing $listing, ImageProcessor $processor, AttachMediaAsset $attach): RedirectResponse
    {
        $this->authorize('update', $listing);

        /** @var list<UploadedFile> $files */
        $files = $request->file('images', []);

        $existing = $listing->media()->count();

        if ($existing + count($files) > ImageLimits::MAX_PER_LISTING) {
            return back()->withErrors([
                'images' => 'Максимум '.ImageLimits::MAX_PER_LISTING.' изображения на обява.',
            ]);
        }

        $stored = 0;

        foreach ($files as $file) {
            try {
                $image = $processor->process($file, 'listings/'.$listing->getKey());
            } catch (InvalidImageUpload $e) {
                // Stop at the first bad file; the ones already stored stay.
                return back()->withErrors(['images' => $e->getMessage()]);
            }

            $attach->handle($listing, $image->toMediaAttributes($file->getClientOriginalName()));
            $stored++;
        }

        return back()->with('status', $stored === 1
            ? 'Изображението е качено.'
            : $stored.' изображения са качени.');
    }

    public function destroy(Listing $listing, MediaAsset $asset): RedirectResponse
    {
        $this->authorize('update', $listing);
        $this->assertBelongsTo($asset, $listing);

        // Delete the row first: a file left on disk is waste, a row pointing at
        // a missing file is a broken page.
        $disk = $asset->disk;
        $path = $asset->path;

        $asset->delete();

        Storage::disk($disk)->delete($path);

        return back()->with('status', 'Изображението е премахнато.');
    }

    /** Moves an asset to the front of the gallery, which is what the card shows. */
    public function cover(Listing $listing, MediaAsset $asset, RepositionMediaAsset $reposition): RedirectResponse
    {
        $this->authorize('update', $listing);
        $this->assertBelongsTo($asset, $listing);

        $reposition->handle($asset);

        return back()->with('status', 'Изображението е зададено като основно.');
    }

    /**
     * A member may own the listing and still pass an asset id belonging to a
     * different one; the morph pair is what ties them together.
     */
    private function assertBelongsTo(MediaAsset $asset, Listing $listing): void
    {
        abort_unless(
            $asset->mediable_type === $listing::class && (int) $asset->mediable_id === (int) $listing->getKey(),
            Response::HTTP_NOT_FOUND
        );
    }
}
