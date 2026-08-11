<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Category create/edit input.
 *
 * The route model binding supplies the category on edit; `slug` is unique
 * across the table but must ignore the row being edited, otherwise saving a
 * category without touching its slug would fail against itself.
 *
 * parent_id is validated as "exists" only. It cannot be checked for cycles
 * here — that needs the subtree, which is a Model read and off-limits to a
 * FormRequest under the layer rules. CategoryController does it.
 */
class CategoryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('category')?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($id),
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'icon' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Слъгът може да съдържа само малки латински букви, цифри и тире.',
        ];
    }
}
