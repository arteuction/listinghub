<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

final class DeleteCategory
{
    public function handle(Category $category): void
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Категорията има подкатегории. Преместете ги преди изтриване.',
            ]);
        }

        if ($category->listings()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Категорията съдържа обяви. Преместете ги или деактивирайте категорията.',
            ]);
        }

        $category->delete();
    }
}
