<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ListingFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->company();

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'plan_id' => null,
            'settlement_id' => null,
            'organization_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->paragraph(),
            'status' => ListingStatus::Draft->value,
            'is_featured' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ListingStatus::Published->value,
            'published_at' => now(),
        ]);
    }
}
