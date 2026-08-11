<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Full replacement of a product's attribute rows — the same contract as
 * SyncListingHours: the payload IS the complete state, and a row absent from
 * it is deleted rather than left behind. A patch-style sync would let a stale
 * "Гаранция: 2 години" survive after the owner removed it from the form.
 *
 * Attribute definitions (the name/slug pairs) are global and shared across
 * products — find-or-create by slug, so two products both declaring "Цвят"
 * reference one attributes row. Values are per-product and carry the display
 * order the owner wrote them in.
 */
final class SyncProductAttributes
{
    /** @param list<array{name: string, value: string}> $rows */
    public function execute(Product $product, array $rows): void
    {
        DB::transaction(function () use ($product, $rows): void {
            $product->attributeValues()->delete();

            $seen = [];
            $position = 0;

            foreach ($rows as $row) {
                $name = trim($row['name']);
                $value = trim($row['value']);

                if ($name === '' || $value === '') {
                    continue;
                }

                $slug = Str::slug($name);

                if ($slug === '') {
                    $slug = 'atribut-'.substr(hash('sha256', $name), 0, 8);
                }

                // The schema enforces one value per attribute per product;
                // the LAST occurrence of a duplicated name wins so what the
                // owner sees lowest in the form is what is stored.
                if (isset($seen[$slug])) {
                    AttributeValue::query()
                        ->where('product_id', $product->getKey())
                        ->where('attribute_id', $seen[$slug])
                        ->update(['value' => $value]);

                    continue;
                }

                $attribute = Attribute::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $name],
                );

                AttributeValue::create([
                    'attribute_id' => $attribute->getKey(),
                    'product_id' => $product->getKey(),
                    'value' => $value,
                    'sort_order' => $position,
                ]);

                $seen[$slug] = $attribute->getKey();
                $position++;
            }
        });
    }
}
