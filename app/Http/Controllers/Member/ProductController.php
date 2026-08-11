<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\SyncProductAttributes;
use App\Actions\Products\UpdateProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\ProductRequest;
use App\Models\Listing;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Products for a listing the member owns.
 *
 * Managing products is part of managing the listing itself, so every action
 * authorises against the PARENT listing (`ListingPolicy::update`) rather than
 * the product row — a member with no products yet still needs to reach
 * create(). destroy()/edit()/update() additionally confirm the product
 * belongs to that exact listing (same reasoning as ListingMediaController).
 */
class ProductController extends Controller
{
    public function index(Listing $listing): View
    {
        $this->authorize('update', $listing);

        return view('member.products.index', [
            'listing' => $listing,
            'products' => $listing->products()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(Listing $listing): View
    {
        $this->authorize('update', $listing);

        return view('member.products.form', ['listing' => $listing, 'product' => null]);
    }

    public function store(ProductRequest $request, Listing $listing, CreateProduct $create, SyncProductAttributes $syncAttributes): RedirectResponse
    {
        $this->authorize('update', $listing);

        $product = $create->execute($listing, $request->validated());
        $syncAttributes->execute($product, $this->attributeRows($request));

        return redirect()->route('member.listings.products.index', $listing)
            ->with('status', 'Продуктът е добавен.');
    }

    public function edit(Listing $listing, Product $product): View
    {
        $this->authorize('update', $listing);
        $this->assertBelongsTo($product, $listing);

        return view('member.products.form', ['listing' => $listing, 'product' => $product]);
    }

    public function update(ProductRequest $request, Listing $listing, Product $product, UpdateProduct $update, SyncProductAttributes $syncAttributes): RedirectResponse
    {
        $this->authorize('update', $listing);
        $this->assertBelongsTo($product, $listing);

        $update->execute($product, $request->validated());
        $syncAttributes->execute($product, $this->attributeRows($request));

        return redirect()->route('member.listings.products.index', $listing)
            ->with('status', 'Промените са запазени.');
    }

    public function destroy(Listing $listing, Product $product): RedirectResponse
    {
        $this->authorize('update', $listing);
        $this->assertBelongsTo($product, $listing);

        $product->delete();

        return redirect()->route('member.listings.products.index', $listing)
            ->with('status', 'Продуктът е изтрит.');
    }

    /** @return list<array{name: string, value: string}> */
    private function attributeRows(ProductRequest $request): array
    {
        /** @var list<array{name: string, value: string}> $rows */
        $rows = array_values($request->validated()['attributes'] ?? []);

        return $rows;
    }

    private function assertBelongsTo(Product $product, Listing $listing): void
    {
        abort_unless(
            (int) $product->listing_id === (int) $listing->getKey(),
            Response::HTTP_NOT_FOUND
        );
    }
}
