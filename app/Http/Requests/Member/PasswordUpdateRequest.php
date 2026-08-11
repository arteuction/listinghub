<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordUpdateRequest extends FormRequest
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
            // `current_password` proves the session belongs to the account
            // owner, so a hijacked session cannot lock the owner out.
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Текущата парола е грешна.',
            'password.confirmed' => 'Двете пароли не съвпадат.',
        ];
    }
}
