<?php

declare(strict_types=1);

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

class ClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership/eligibility checked in the controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            // Size/type here are only the first, cheap gate; the REAL
            // verification (content sniffing, re-encoding) happens in
            // ClaimDocumentProcessor and does not trust these rules.
            'document' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
