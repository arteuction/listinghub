<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the active locale from, in order: authenticated user preference,
 * session, then the configured fallback. No tenant logic involved.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = ['en']; // extended by the Language settings in later iterations.

        // $request->user() is typed Authenticatable, which declares no $locale;
        // narrow to the concrete model before reading it.
        $user = $request->user();

        $locale = ($user instanceof User ? $user->locale : null)
            ?? $request->session()->get('locale')
            ?? config('app.locale');

        if (in_array($locale, $available, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
