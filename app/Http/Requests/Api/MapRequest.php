<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Support\BBox;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query-string validation for GET /api/catalog/map.
 * Mirrors ListingIndexRequest for the catalog filters; adds bbox.
 */
class MapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bbox'         => ['required', 'string'],
            'q'            => ['nullable', 'string', 'max:200'],
            'category'     => ['nullable', 'string', 'max:100'],
            'region'       => ['nullable', 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'settlement'   => ['nullable', 'string', 'max:100'],
        ];
    }

    /** Parses and validates the bbox string; returns null on malformed input. */
    public function bbox(): ?BBox
    {
        try {
            $bbox = BBox::fromString((string) $this->query('bbox', ''));
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $bbox->isWithinBulgaria() ? $bbox : null;
    }

    public function keyword(): ?string
    {
        $q = trim((string) $this->query('q', ''));

        return $q === '' ? null : $q;
    }
}
