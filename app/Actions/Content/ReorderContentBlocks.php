<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\ContentBlock;
use App\Support\SparseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reorders content blocks within a single owner scope.
 *
 * Accepts an ordered list of block IDs and assigns sparse integer positions.
 * All IDs must belong to the same owner; passing blocks from a different owner
 * is rejected to prevent cross-page poisoning.
 *
 * On each reorder the positions are always fully rebalanced (STEP × index)
 * which is safe: the list is small and under an admin lock, so the O(n)
 * write is not a concern.
 */
final class ReorderContentBlocks
{
    /**
     * @param  list<int>   $orderedIds  Block primary keys in the desired order.
     * @param  Model       $owner       The page, category, or other polymorphic owner.
     */
    public function handle(array $orderedIds, Model $owner): void
    {
        if ($orderedIds === []) {
            return;
        }

        if (count($orderedIds) !== count(array_unique($orderedIds))) {
            throw new InvalidArgumentException('Reorder list contains duplicate block IDs.');
        }

        DB::transaction(function () use ($orderedIds, $owner): void {
            $ownerType = $owner->getMorphClass();
            $ownerId   = (int) $owner->getKey();

            // Verify all submitted IDs belong to this owner (no cross-owner writes).
            $existing = ContentBlock::query()
                ->where('owner_type', $ownerType)
                ->where('owner_id', $ownerId)
                ->whereIn('id', $orderedIds)
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            $missing = array_diff($orderedIds, $existing);
            if ($missing !== []) {
                throw new InvalidArgumentException(
                    'Block IDs not found in this owner scope: '.implode(', ', $missing)
                );
            }

            $newPositions = SparseOrder::rebalance(count($orderedIds));

            foreach ($orderedIds as $index => $id) {
                ContentBlock::query()
                    ->where('id', $id)
                    ->update(['sort_order' => $newPositions[$index]]);
            }
        });
    }
}
