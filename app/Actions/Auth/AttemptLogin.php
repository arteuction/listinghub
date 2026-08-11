<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Credential + account-status policy for session login.
 *
 * Lives in Actions (not in the FormRequest) so the HTTP layer never queries
 * models directly — the boundary Deptrac enforces. Returns true on success,
 * false for every rejection reason; the caller renders one generic message so
 * a wrong password, an unknown email and a suspended account stay
 * indistinguishable.
 */
final class AttemptLogin
{
    /**
     * A valid, precomputed bcrypt hash used when no user matches, so a password
     * verification runs in BOTH cases and an unknown email cannot be told apart
     * from an existing one by response timing. Never regenerated per request.
     */
    private const DUMMY_HASH = '$2y$10$P9Vo4EkeMT/jjDosFl8eZuOcD.jtEXdh4yrfwz2SAEgYY3qEBzjQi';

    public function execute(string $email, string $password): bool
    {
        // Portable, case-insensitive email match (SQLite is case-sensitive on
        // `=`, MySQL collation is not — normalise both sides with lower()).
        // The annotation states the nullability that whereRaw() loses for the
        // analyser: an unknown email legitimately yields null here.
        /** @var User|null $user */
        $user = User::query()
            ->whereRaw('lower(email) = ?', [Str::lower($email)])
            ->first();

        // Always run one password check (dummy hash when no user) — constant-ish
        // time so an unknown email is indistinguishable from a wrong password.
        $passwordOk = Hash::check($password, $user?->password ?? self::DUMMY_HASH);

        if ($user === null || ! $passwordOk || $user->status !== UserStatus::Active) {
            return false;
        }

        Auth::login($user);

        return true;
    }
}
