<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the admin group: a user who becomes suspended AFTER logging in must
 * lose access immediately, not merely be blocked at the next login. On a
 * non-active status the session is terminated and the user is bounced to login.
 * (Applied after `auth`; the logout route deliberately does NOT use this.)
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Re-check the LIVE status from the database (not the possibly-cached
        // session model), so a mid-session suspension takes effect immediately.
        $fresh = $user?->fresh();

        if ($user !== null && ($fresh === null || $fresh->status !== UserStatus::Active)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account is not active.',
            ]);
        }

        return $next($request);
    }
}
