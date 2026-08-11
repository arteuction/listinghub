<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Applied to the whole "web" group: block the app until the installer
        // has completed, and resolve the active locale on every request.
        $middleware->web(append: [
            EnsureInstalled::class,
            SetLocale::class,
        ]);

        // The installer gate also protects the API surface (returns 503 JSON
        // until installation completes).
        $middleware->api(prepend: [
            EnsureInstalled::class,
        ]);

        // Guests hitting a protected route are sent to the login screen (with
        // the intended URL remembered by the framework).
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Named aliases used by routes/policies.
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'active' => EnsureActiveUser::class,
            'guest' => RedirectIfAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
