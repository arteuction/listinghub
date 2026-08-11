<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class ListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is decided by ListingPolicy in the controller, which is the
        // only place that knows which listing is being touched.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            // Bulgaria-only: the id must exist in the seeded EKATTE geography,
            // so a listing cannot be pinned to a place that is not in the country.
            'settlement_id' => ['nullable', 'integer', 'exists:settlements,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            // Scheme-restricted: a javascript: or data: URL rendered as a link
            // on the public page would be a stored-XSS vector.
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'website.url' => 'Адресът трябва да започва с http:// или https://.',
            'settlement_id.exists' => 'Изберете населено място от списъка.',
        ];
    }
}
