<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ListingHourExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership decided by ListingPolicy in the controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'is_closed' => ['nullable', 'boolean'],
            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $isClosed = (bool) ($this->input('is_closed') ?? true);
            if ($isClosed) {
                return;
            }

            $opens = $this->input('opens_at');
            $closes = $this->input('closes_at');

            if (($opens === null) !== ($closes === null)) {
                $validator->errors()->add('closes_at', 'Задайте едновременно начален и краен час, или оставете деня затворен.');

                return;
            }

            if ($opens !== null && $closes !== null && $closes <= $opens) {
                $validator->errors()->add('closes_at', 'Крайният час трябва да е след началния.');
            }
        });
    }
}
