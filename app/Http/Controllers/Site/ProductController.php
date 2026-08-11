<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\ListingStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Product;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public product detail, scoped under its listing:
 * /listings/{listing}/products/{product} — both slugs.
 *
 * Visibility mirrors the listing page rule exactly: a product that is not
 * Published, or whose parent listing is not Published, must be
 * indistinguishable from one that does not exist. The product is resolved
 * INSIDE the listing's scope, so a slug (or id) from a different listing
 * 404s even if it exists elsewhere — cross-listing probing reveals nothing.
 */
class ProductController extends Controller
{
    public function show(Listing $listing, string $productSlug): View
    {
        abort_unless(
            $listing->status === ListingStatus::Published && $listing->published_at !== null,
            Response::HTTP_NOT_FOUND
        );

        /** @var Product|null $product */
        $product = $listing->products()
            ->where('slug', $productSlug)
            ->where('status', ProductStatus::Published->value)
            ->first();

        abort_unless($product !== null, Response::HTTP_NOT_FOUND);

        $product->load([
            'media',
            'attributeValues.attribute',
        ]);

        $listing->load(['settlement.municipality.region', 'category']);

        return view('site.products.show', [
            'listing' => $listing,
            'product' => $product,
        ]);
    }
}
