<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Gives every controller $this->authorize(), which is how ownership checks
    // reach the policies (Laravel auto-discovers App\Models\Foo ->
    // App\Policies\FooPolicy).
    use AuthorizesRequests;
}
