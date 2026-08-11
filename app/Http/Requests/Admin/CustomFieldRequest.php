<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\CustomFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape validation for create/update of a custom field definition. Domain
 * invariants (I1–I11) live in the actions, not here — this only guards the
 * incoming HTTP shape.
 */
class CustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already behind auth + permission:manage settings
    }

    protected function prepareForValidation(): void
    {
        // Normalise absent HTML checkboxes to explicit booleans.
        $this->merge([
            'is_required' => $this->boolean('is_required'),
            'searchable' => $this->boolean('searchable'),
            'filterable' => $this->boolean('filterable'),
            'sortable' => $this->boolean('sortable'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'is_required' => ['boolean'],
            'searchable' => ['boolean'],
            'filterable' => ['boolean'],
            'sortable' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'options' => ['nullable', 'array'],
            'options.*.value' => ['required_with:options', 'string', 'max:255'],
            'options.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
