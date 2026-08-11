<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Support\SparseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mediable_type' => Listing::class,
            'mediable_id' => Listing::factory(),
            'collection' => 'gallery',
            'disk' => 'public',
            'path' => 'media/'.Str::random(16).'.jpg',
            'file_name' => Str::random(8).'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(1000, 5_000_000),
            'sort_order' => SparseOrder::STEP,
            'metadata' => null,
        ];
    }

    /** Attach to an existing mediable at an explicit position. */
    public function forOwner(object $mediable, int $sortOrder, string $collection = 'gallery'): static
    {
        return $this->state(fn () => [
            'mediable_type' => $mediable::class,
            'mediable_id' => $mediable->getKey(),
            'collection' => $collection,
            'sort_order' => $sortOrder,
        ]);
    }
}
