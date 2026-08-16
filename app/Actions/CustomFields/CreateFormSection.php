<?php

declare(strict_types=1);

namespace App\Actions\CustomFields;

use App\Models\Category;
use App\Models\FormSection;
use Illuminate\Support\Facades\DB;

final class CreateFormSection
{
    public function handle(
        Category $category,
        string $title,
        ?string $description = null,
        int $sortOrder = 1000,
        bool $isCollapsible = false,
    ): FormSection {
        return DB::transaction(function () use ($category, $title, $description, $sortOrder, $isCollapsible): FormSection {
            return FormSection::query()->create([
                'category_id'    => $category->id,
                'title'          => trim($title),
                'description'    => $description ? trim($description) : null,
                'sort_order'     => max(0, $sortOrder),
                'is_collapsible' => $isCollapsible,
            ]);
        });
    }
}
