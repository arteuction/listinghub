<?php

declare(strict_types=1);

namespace App\Actions\CustomFields;

use App\Models\Category;
use App\Models\CustomField;
use App\Support\SparseOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReorderFormFields
{
    public function handle(array $orderedIds, Category $category): void
    {
        if ($orderedIds === []) {
            return;
        }

        if (count($orderedIds) !== count(array_unique($orderedIds))) {
            throw new InvalidArgumentException('Reorder list contains duplicate field IDs.');
        }

        DB::transaction(function () use ($orderedIds, $category): void {
            $existing = CustomField::query()
                ->where('category_id', $category->id)
                ->whereIn('id', $orderedIds)
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            $missing = array_diff($orderedIds, $existing);
            if ($missing !== []) {
                throw new InvalidArgumentException(
                    'Field IDs not found in this category: ' . implode(', ', $missing)
                );
            }

            $newPositions = SparseOrder::rebalance(count($orderedIds));

            foreach ($orderedIds as $index => $id) {
                CustomField::query()
                    ->where('id', $id)
                    ->update(['sort_order' => $newPositions[$index]]);
            }
        });
    }
}
