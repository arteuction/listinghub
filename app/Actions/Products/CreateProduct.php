<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Models\Listing;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Creates a product under a listing, always as a Draft.
 *
 * The slug is unique per listing (schema constraint `[listing_id, slug]`),
 * not globally — two different listings may each sell a product called
 * "Standard package" with the same slug.
 */
final class CreateProduct
{
    /** @param array<string, mixed> $data */
    public function execute(Listing $listing, array $data): Product
    {
        return Product::create([
            'listing_id' => $listing->getKey(),
            'organization_id' => $listing->organization_id,
            'name' => (string) $data['name'],
            'slug' => $this->uniqueSlug($listing, (string) $data['name']),
            'description' => $data['description'] ?? null,
            'price_minor' => (int) $data['price_minor'],
            'currency' => strtoupper((string) ($data['currency'] ?? config('listinghub.payments.currency', 'USD'))),
            'status' => ProductStatus::Draft->value,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    private function uniqueSlug(Listing $listing, string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'produkt';
        }

        $exists = fn (string $slug): bool => Product::query()
            ->where('listing_id', $listing->getKey())
            ->where('slug', $slug)
            ->exists();

        if (! $exists($base)) {
            return $base;
        }

        do {
            $candidate = $base.'-'.Str::lower(Str::random(6));
        } while ($exists($candidate));

        return $candidate;
    }
}
