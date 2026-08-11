<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route already sits behind `auth`; this layer adds nothing.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                // getAuthIdentifier() rather than ->id: the Requests layer must
                // not depend on the model (enforced by Deptrac).
                Rule::unique('users', 'email')->ignore($this->user()?->getAuthIdentifier()),
            ],
            'about' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['email.unique' => 'Този имейл вече се използва от друг профил.'];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }
}
