<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the whole application behind the installer.
 *
 * While the lock file is absent every non-installer web request is redirected
 * to the wizard and every API/JSON request gets a 503. Once installed, the
 * installer routes return 404 so the wizard can never be re-run.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = static::isInstalled();
        $onInstaller = $request->is('install', 'install/*');

        if (! $installed) {
            if ($request->is('api', 'api/*') || $request->expectsJson()) {
                abort(503, 'Application is not installed yet.');
            }

            if (! $onInstaller) {
                return redirect()->route('install.welcome');
            }
        }

        if ($installed && $onInstaller) {
            abort(404);
        }

        return $next($request);
    }

    public static function isInstalled(): bool
    {
        return file_exists(storage_path('app/installed.lock'));
    }
}
