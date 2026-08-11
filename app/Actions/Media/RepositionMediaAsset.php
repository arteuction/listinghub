<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\MediaAsset;
use App\Support\SparseOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Moves a media asset within its own scope (mediable_type + mediable_id +
 * collection). Neighbours are resolved by POSITION IN THE LOCKED, ORDERED
 * collection (sort_order, id) — not by a numeric `sort_order >` comparison —
 * so equal sort_order values (the default 1000 makes these normal) are handled
 * deterministically. Only this scope's rows are locked, so repositioning one
 * gallery never touches another.
 */
class RepositionMediaAsset
{
    /**
     * Place $asset immediately after $target (or at the front when $target is
     * null). Returns the asset with its new sort_order.
     */
    public function handle(MediaAsset $asset, ?MediaAsset $target = null): MediaAsset
    {
        if ($target !== null && $target->is($asset)) {
            return $asset; // no-op: cannot move an asset relative to itself
        }

        if ($target !== null && ! $this->sameScope($asset, $target)) {
            throw new InvalidArgumentException('Target media asset belongs to a different scope.');
        }

        return DB::transaction(function () use ($asset, $target): MediaAsset {
            $scope = MediaAsset::query()
                ->where('mediable_type', $asset->mediable_type)
                ->where('mediable_id', $asset->mediable_id)
                ->where('collection', $asset->collection)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (! $scope->contains(fn (MediaAsset $m) => $m->is($asset))) {
                throw new InvalidArgumentException('Media asset no longer exists in its scope.');
            }
            if ($target !== null && ! $scope->contains(fn (MediaAsset $m) => $m->is($target))) {
                throw new InvalidArgumentException('Target media asset no longer exists.');
            }

            /** @var Collection<int, MediaAsset> $others */
            $others = $scope->reject(fn (MediaAsset $m) => $m->is($asset))->values();

            // Index in $others AFTER which the asset should sit (-1 = front).
            $afterIdx = $target === null
                ? -1
                : (int) $others->search(fn (MediaAsset $m) => $m->is($target));

            $lower = $afterIdx === -1 ? 0 : $others[$afterIdx]->sort_order;
            $upper = $others->get($afterIdx + 1)?->sort_order;

            $newPos = $upper === null
                ? SparseOrder::append($lower)
                : SparseOrder::between($lower, $upper);

            if ($newPos === null) {
                return $this->rebalance($others, $asset, $afterIdx);
            }

            $asset->sort_order = $newPos;
            $asset->save();

            return $asset;
        });
    }

    /**
     * Reassign evenly spaced positions to the whole scope in the desired final
     * order, then return the moved asset.
     *
     * @param  Collection<int, MediaAsset>  $others
     */
    private function rebalance(Collection $others, MediaAsset $asset, int $afterIdx): MediaAsset
    {
        $ordered = [];
        if ($afterIdx === -1) {
            $ordered[] = $asset;
        }
        foreach ($others as $i => $m) {
            $ordered[] = $m;
            if ($i === $afterIdx) {
                $ordered[] = $asset;
            }
        }

        $positions = SparseOrder::rebalance(count($ordered));
        foreach ($ordered as $i => $m) {
            $m->sort_order = $positions[$i];
            $m->save();
        }

        return $asset;
    }

    private function sameScope(MediaAsset $a, MediaAsset $b): bool
    {
        return $a->mediable_type === $b->mediable_type
            && (string) $a->mediable_id === (string) $b->mediable_id
            && $a->collection === $b->collection;
    }
}
