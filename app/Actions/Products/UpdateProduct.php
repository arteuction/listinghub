<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Models\Product;

/**
 * Applies owner/staff-editable fields.
 *
 * `status` is only ever written here when the caller is staff (the request
 * layer strips it from a member's payload — see ProductRequest) or is one of
 * the two values a member may freely toggle between (Draft/Published);
 * Suspended is a staff-only value enforced by ProductRequest::rules().
 */
final class UpdateProduct
{
    /** @param array<string, mixed> $data */
    public function execute(Product $product, array $data): Product
    {
        $product->update([
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'price_minor' => (int) $data['price_minor'],
            'currency' => strtoupper((string) ($data['currency'] ?? $product->currency)),
            'sort_order' => (int) ($data['sort_order'] ?? $product->sort_order),
            'status' => isset($data['status']) ? ProductStatus::from($data['status'])->value : $product->status->value,
        ]);

        return $product;
    }
}
