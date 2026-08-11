<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Member self-registration. New accounts are active but unverified — the
 * verification notice gates anything that needs a confirmed address.
 */
class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, RegisterUser $register): RedirectResponse
    {
        $user = $register->execute(
            (string) $request->validated('name'),
            (string) $request->validated('email'),
            (string) $request->validated('password'),
        );

        Auth::login($user);

        // A fresh session id for the new privilege level.
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
