<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A member may only ever set Draft or Published — Suspended is a
 * staff/moderation action (see Admin\ProductRequest), never a value the
 * owner can choose for their own row.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is decided by ProductPolicy in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price_minor' => ['required', 'integer', 'min:0', 'max:999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'in:draft,published'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
