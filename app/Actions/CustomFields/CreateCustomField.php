<?php

declare(strict_types=1);

namespace App\Actions\CustomFields;

use App\Enums\CustomFieldType;
use App\Exceptions\CustomFieldConflict;
use App\Models\Category;
use App\Models\CustomField;
use App\Support\CustomFieldDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Creates a custom field definition on a category, using the SAME definition
 * validator as UpdateCustomField (I10 flags, I11 options). On create no values
 * exist yet, so the I5/I6/I9 immutability guards do not apply.
 */
class CreateCustomField
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Category $category, array $attributes): CustomField
    {
        $type = $attributes['type'] instanceof CustomFieldType
            ? $attributes['type']
            : CustomFieldType::from((string) $attributes['type']);

        $searchable = (bool) ($attributes['searchable'] ?? false);
        $filterable = (bool) ($attributes['filterable'] ?? false);
        $sortable = (bool) ($attributes['sortable'] ?? false);

        CustomFieldDefinition::assertFlags($type, $searchable, $filterable, $sortable);
        $options = CustomFieldDefinition::normalizeOptions($type, $attributes['options'] ?? null);

        $key = (string) $attributes['key'];

        return DB::transaction(function () use ($category, $attributes, $type, $key, $options, $searchable, $filterable, $sortable): CustomField {
            if (CustomField::query()->where('category_id', $category->id)->where('key', $key)->exists()) {
                throw CustomFieldConflict::make("A field with key '{$key}' already exists in this category.");
            }

            return CustomField::query()->create([
                'category_id' => $category->id,
                'label' => (string) $attributes['label'],
                'key' => $key,
                'type' => $type,
                'is_required' => (bool) ($attributes['is_required'] ?? false),
                'options' => $options,
                'searchable' => $searchable,
                'filterable' => $filterable,
                'sortable' => $sortable,
                'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            ]);
        });
    }
}
