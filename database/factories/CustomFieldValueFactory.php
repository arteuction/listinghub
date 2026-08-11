<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CustomField;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomFieldValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'custom_field_id' => CustomField::factory(),
            'listing_id' => Listing::factory(),
            'value_text' => fake()->sentence(),
            'value_string' => null,
            'value_decimal' => null,
            'value_boolean' => null,
        ];
    }
}
