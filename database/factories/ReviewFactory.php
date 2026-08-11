<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ModerationStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'user_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'body' => fake()->sentence(),
            'status' => ModerationStatus::Pending->value,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => ModerationStatus::Approved->value]);
    }
}
