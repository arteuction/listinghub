<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Reserved for the JSON endpoints (ajax geo lookups, autocomplete, payment
| webhooks are registered in web.php with signature verification). Added in
| later iterations.
|
*/

Route::get('/ping', fn () => response()->json(['pong' => true]));
