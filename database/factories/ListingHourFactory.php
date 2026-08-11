<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

class ListingHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'is_closed' => false,
        ];
    }
}
