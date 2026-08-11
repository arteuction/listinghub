<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SettlementFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->city();

        return [
            'municipality_id' => Municipality::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'ekatte' => null,
            'type' => fake()->randomElement(['city', 'town', 'village']),
            'latitude' => fake()->latitude(41.2, 44.2),
            'longitude' => fake()->longitude(22.4, 28.6),
        ];
    }
}
