<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a member account.
 *
 * The role assignment and the account row are written in one transaction, so a
 * failure cannot leave a user without the `member` role — which would be an
 * account that exists but can do nothing. Firing Registered sends the
 * verification mail; the account is usable but unverified until then.
 */
final class RegisterUser
{
    public function execute(string $name, string $email, string $password): User
    {
        $user = DB::transaction(function () use ($name, $email, $password): User {
            $user = User::create([
                'name' => $name,
                // Stored lowercase: logins match case-insensitively, so the
                // column must not hold two addresses that differ only in case.
                'email' => mb_strtolower($email),
                'password' => Hash::make($password),
                'status' => UserStatus::Active->value,
                'locale' => config('app.locale'),
            ]);

            $user->assignRole('member');

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
