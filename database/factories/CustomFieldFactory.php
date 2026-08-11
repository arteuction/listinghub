<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomFieldType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CustomFieldFactory extends Factory
{
    public function definition(): array
    {
        $key = 'field_'.Str::lower(Str::random(8));

        return [
            'category_id' => Category::factory(),
            'label' => ucfirst(fake()->word()),
            'key' => $key,
            'type' => CustomFieldType::Text,
            'options' => null,
            'is_required' => false,
            'sort_order' => 0,
            'searchable' => false,
            'filterable' => false,
            'sortable' => false,
        ];
    }

    public function type(CustomFieldType $type, ?array $options = null): static
    {
        return $this->state(fn () => ['type' => $type, 'options' => $options]);
    }

    /** @param list<string> $values */
    public function select(array $values): static
    {
        $options = array_map(fn ($v) => ['value' => $v, 'label' => ucfirst($v)], $values);

        return $this->state(fn () => ['type' => CustomFieldType::Select, 'options' => $options]);
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }

    public function filterable(): static
    {
        return $this->state(fn () => ['filterable' => true]);
    }

    public function sortable(): static
    {
        return $this->state(fn () => ['sortable' => true]);
    }
}
