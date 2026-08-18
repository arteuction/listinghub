<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;

final class CreateCategory
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Category
    {
        /** @var Category $category */
        $category = Category::query()->create([
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return $category;
    }
}
