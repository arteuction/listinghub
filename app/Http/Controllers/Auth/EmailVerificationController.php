<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Email confirmation. The verify route is signed, so the link cannot be forged
 * or replayed after it expires; EmailVerificationRequest performs the id/hash
 * check before the action body runs.
 */
class EmailVerificationController extends Controller
{
    // These actions sit behind the `auth` middleware, so the user is present.
    // Note for future edits: do not reach for `?->` on $request->user() —
    // Larastan resolves it to a non-null model and flags the nullsafe.
    public function notice(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if ($user !== null && $user->hasVerifiedEmail()) {
            return redirect()->route('profile.edit');
        }

        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('profile.edit')->with('status', 'Имейл адресът е потвърден.');
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || $user->hasVerifiedEmail()) {
            return redirect()->route('profile.edit');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'Изпратихме нов линк за потвърждение.');
    }
}
