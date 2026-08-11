<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5; // per minute, per email+IP

    /**
     * A valid, precomputed bcrypt hash used when no user matches, so a password
     * verification runs in BOTH cases and an unknown email cannot be told apart
     * from an existing one by response timing. Never regenerated per request.
     */
    private const DUMMY_HASH = '$2y$10$P9Vo4EkeMT/jjDosFl8eZuOcD.jtEXdh4yrfwz2SAEgYY3qEBzjQi';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Validate credentials and log the user in. One generic error covers a wrong
     * password, an unknown email AND a suspended account — never reveal which.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Portable, case-insensitive email match (SQLite is case-sensitive on
        // `=`, MySQL collation is not — normalise both sides with lower()).
        $user = User::query()
            ->whereRaw('lower(email) = ?', [Str::lower((string) $this->input('email'))])
            ->first();

        // Always run one password check (dummy hash when no user) — constant-ish
        // time so an unknown email is indistinguishable from a wrong password.
        $passwordOk = Hash::check((string) $this->input('password'), $user?->password ?? self::DUMMY_HASH);

        $ok = $user !== null && $passwordOk && $user->status === UserStatus::Active;

        if (! $ok) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Auth::login($user);
    }

    private function ensureIsNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }
    }

    public function throttleKey(): string
    {
        return Str::lower((string) $this->input('email')).'|'.$this->ip();
    }
}
