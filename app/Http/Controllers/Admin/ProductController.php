<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\SyncProductAttributes;
use App\Actions\Products\UpdateProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Listing;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin CRUD for any listing's products (behind `permission:manage
 * products` at the route group — see routes/web.php). Unlike the member
 * side, status may be set to Suspended here (Admin\ProductRequest allows it).
 */
class ProductController extends Controller
{
    public function index(Listing $listing): View
    {
        return view('admin.products.index', [
            'listing' => $listing,
            'products' => $listing->products()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(Listing $listing): View
    {
        return view('admin.products.form', ['listing' => $listing, 'product' => null]);
    }

    public function store(ProductRequest $request, Listing $listing, CreateProduct $create, SyncProductAttributes $syncAttributes): RedirectResponse
    {
        $product = $create->execute($listing, $request->validated());
        $syncAttributes->execute($product, $this->attributeRows($request));

        return redirect()->route('admin.listings.products.index', $listing);
    }

    public function edit(Listing $listing, Product $product): View
    {
        $this->assertOwned($listing, $product);

        return view('admin.products.form', ['listing' => $listing, 'product' => $product]);
    }

    public function update(ProductRequest $request, Listing $listing, Product $product, UpdateProduct $update, SyncProductAttributes $syncAttributes): RedirectResponse
    {
        $this->assertOwned($listing, $product);

        $update->execute($product, $request->validated());
        $syncAttributes->execute($product, $this->attributeRows($request));

        return redirect()->route('admin.listings.products.index', $listing);
    }

    public function destroy(Listing $listing, Product $product): RedirectResponse
    {
        $this->assertOwned($listing, $product);

        $product->delete();

        return redirect()->route('admin.listings.products.index', $listing);
    }

    /** @return list<array{name: string, value: string}> */
    private function attributeRows(ProductRequest $request): array
    {
        /** @var list<array{name: string, value: string}> $rows */
        $rows = array_values($request->validated()['attributes'] ?? []);

        return $rows;
    }

    private function assertOwned(Listing $listing, Product $product): void
    {
        abort_unless((int) $product->listing_id === (int) $listing->getKey(), 404);
    }
}
