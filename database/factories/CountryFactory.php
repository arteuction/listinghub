<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CountryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->country();

        return [
            'name' => $name,
            'iso2' => strtoupper(fake()->unique()->lexify('??')),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
