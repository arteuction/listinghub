<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\MediaAsset;
use App\Support\SparseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Creates a media asset appended to the end of its scope's order, so a gallery
 * built one upload at a time gets 1000, 2000, 3000 … rather than every row
 * defaulting to 1000.
 */
class AttachMediaAsset
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Model $mediable, array $attributes, string $collection = 'gallery'): MediaAsset
    {
        return DB::transaction(function () use ($mediable, $attributes, $collection): MediaAsset {
            $max = MediaAsset::query()
                ->where('mediable_type', $mediable::class)
                ->where('mediable_id', $mediable->getKey())
                ->where('collection', $collection)
                ->lockForUpdate()
                ->max('sort_order');

            $position = $max === null
                ? SparseOrder::first()
                : SparseOrder::append((int) $max);

            return MediaAsset::create($attributes + [
                'mediable_type' => $mediable::class,
                'mediable_id' => $mediable->getKey(),
                'collection' => $collection,
                'sort_order' => $position,
            ]);
        });
    }
}
