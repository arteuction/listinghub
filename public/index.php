<?php

declare(strict_types=1);

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// First-boot bootstrap: with no .env at all the framework dies on
// MissingAppKeyException BEFORE the web installer can even render
// (verified on a clean deploy). Create .env from .env.example with a
// freshly generated key so /install is reachable without shell access —
// the file is written atomically and only when it does not exist, so an
// existing installation is never touched.
if (! is_file($env = __DIR__.'/../.env') && is_file($example = __DIR__.'/../.env.example')) {
    $contents = (string) file_get_contents($example);
    $key = 'base64:'.base64_encode(random_bytes(32));
    $contents = (string) preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $contents, 1);

    $tmp = $env.'.'.bin2hex(random_bytes(6)).'.tmp';

    if (@file_put_contents($tmp, $contents, LOCK_EX) !== false) {
        @rename($tmp, $env);
    }
}

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
