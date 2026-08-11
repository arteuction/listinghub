<?php

declare(strict_types=1);

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query-string validation for the public catalog.
 *
 * Deliberately validates shape only — no model lookups. Slugs are resolved to
 * models by the controller via route-model binding, and an unknown sort key
 * falls back to the default inside PublicListingQuery.
 */
class ListingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'sort' => ['nullable', 'string', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function keyword(): ?string
    {
        $q = trim((string) $this->query('q', ''));

        return $q === '' ? null : $q;
    }

    public function sortKey(): ?string
    {
        $sort = (string) $this->query('sort', '');

        return $sort === '' ? null : $sort;
    }
}
