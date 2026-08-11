<?php

declare(strict_types=1);

namespace App\Http\Requests\Install;

use Illuminate\Foundation\Http\FormRequest;

class EnvironmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route group is already gated by EnsureInstalled
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:100'],
            'app_url' => ['required', 'url', 'max:255'],
            'db_connection' => ['required', 'in:mysql,pgsql,sqlite'],
            'db_host' => ['required_unless:db_connection,sqlite', 'nullable', 'string', 'max:255'],
            'db_port' => ['required_unless:db_connection,sqlite', 'nullable', 'numeric'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required_unless:db_connection,sqlite', 'nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
