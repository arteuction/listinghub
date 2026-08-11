<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

class ListingHourExceptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'date' => fake()->date(),
            'opens_at' => null,
            'closes_at' => null,
            'is_closed' => true,
            'note' => fake()->optional()->sentence(),
        ];
    }
}
