<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Services\Catalog\PublicListingQuery;
use Illuminate\Validation\ValidationException;

final class UpdateCategory
{
    public function __construct(private readonly PublicListingQuery $catalog) {}

    /** @param array<string, mixed> $data */
    public function handle(Category $category, array $data): Category
    {
        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            $forbidden = $this->catalog->categorySubtreeIds($category);

            if (in_array((int) $parentId, $forbidden, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Категорията не може да е подкатегория на себе си или на свой наследник.',
                ]);
            }
        }

        $category->update([
            'parent_id' => $parentId,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return $category;
    }
}
