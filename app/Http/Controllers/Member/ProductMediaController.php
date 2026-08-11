<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Actions\Media\AttachMediaAsset;
use App\Exceptions\InvalidImageUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\ListingMediaRequest;
use App\Models\Listing;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Services\Media\ImageProcessor;
use App\Support\ImageLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Product gallery, through the SAME verified pipeline as the listing gallery:
 * ListingMediaRequest (content-sniffed mimetypes) → ImageProcessor
 * (decode + re-encode, only pixels survive) → AttachMediaAsset (appended
 * sparse order). Authorisation is against the PARENT listing, and both the
 * product and the asset are checked to belong to their parents — a valid
 * owner must not be able to act on someone else's rows by id.
 */
class ProductMediaController extends Controller
{
    public function store(
        ListingMediaRequest $request,
        Listing $listing,
        Product $product,
        ImageProcessor $processor,
        AttachMediaAsset $attach,
    ): RedirectResponse {
        $this->authorize('update', $listing);
        $this->assertProductBelongsTo($product, $listing);

        /** @var list<UploadedFile> $files */
        $files = $request->file('images', []);

        $existing = $product->media()->count();

        if ($existing + count($files) > ImageLimits::MAX_PER_PRODUCT) {
            return back()->withErrors([
                'images' => 'Максимум '.ImageLimits::MAX_PER_PRODUCT.' изображения на продукт.',
            ]);
        }

        foreach ($files as $file) {
            try {
                $image = $processor->process($file, 'products/'.$product->getKey());
            } catch (InvalidImageUpload $e) {
                return back()->withErrors(['images' => $e->getMessage()]);
            }

            $attach->handle($product, $image->toMediaAttributes($file->getClientOriginalName()));
        }

        return back()->with('status', 'Изображенията са качени.');
    }

    public function destroy(Listing $listing, Product $product, MediaAsset $asset): RedirectResponse
    {
        $this->authorize('update', $listing);
        $this->assertProductBelongsTo($product, $listing);
        $this->assertAssetBelongsTo($asset, $product);

        $disk = $asset->disk;
        $path = $asset->path;

        $asset->delete();

        Storage::disk($disk)->delete($path);

        return back()->with('status', 'Изображението е премахнато.');
    }

    private function assertProductBelongsTo(Product $product, Listing $listing): void
    {
        abort_unless(
            (int) $product->listing_id === (int) $listing->getKey(),
            Response::HTTP_NOT_FOUND
        );
    }

    private function assertAssetBelongsTo(MediaAsset $asset, Product $product): void
    {
        abort_unless(
            $asset->mediable_type === $product::class && (int) $asset->mediable_id === (int) $product->getKey(),
            Response::HTTP_NOT_FOUND
        );
    }
}
