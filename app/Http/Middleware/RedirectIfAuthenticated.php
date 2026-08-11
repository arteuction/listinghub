<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps already-authenticated users off the login routes, so a logged-in user
 * cannot POST /login and switch identity without logging out first. Redirects
 * to the site root — a safe target for EVERY role (redirecting to /admin would
 * bounce a non-admin into a 403).
 */
class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return $next($request);
    }
}
