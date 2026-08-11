<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Step 1 of the reset flow: mail a signed reset token.
 *
 * The response is deliberately identical whether or not the address exists —
 * a differing message would turn this form into an account-enumeration oracle.
 */
class PasswordResetLinkController extends Controller
{
    private const GENERIC = 'Ако съществува регистрация с този имейл, изпратихме линк за смяна на паролата.';

    public function show(): View
    {
        return view('auth.forgot-password');
    }

    public function store(PasswordResetLinkRequest $request): RedirectResponse
    {
        // The broker's own throttle (config/auth.php: passwords.users.throttle)
        // stops repeated sends to the same address.
        Password::sendResetLink(['email' => (string) $request->validated('email')]);

        return back()->with('status', self::GENERIC);
    }
}
