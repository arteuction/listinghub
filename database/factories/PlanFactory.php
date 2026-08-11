<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlanFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'organization_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(),
            'price_minor' => fake()->numberBetween(0, 9900),
            'currency' => 'USD',
            'interval' => BillingInterval::Month->value,
            'listing_limit' => fake()->numberBetween(1, 20),
            'gallery_limit' => fake()->numberBetween(3, 50),
            'is_featured' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
