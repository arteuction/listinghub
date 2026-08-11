<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind auth + permission:manage settings
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // The bound model is read through the Model contract rather than the
        // concrete App\Models\User: the Requests layer must not depend on
        // Models (enforced by deptrac.yaml), and only the key is needed here.
        $bound = $this->route('user');
        $ignoreId = $bound instanceof Model ? $bound->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($ignoreId),
            ],
            'status' => ['required', 'in:active,suspended'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
