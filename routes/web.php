<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CustomFieldController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Iteration 1 registers only the skeleton entrypoints. Feature routes
| (public browsing, member panel, admin panel, payments) are added in
| later iterations per docs/ARCHITECTURE.md §13.
|
*/

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ListingController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Public landing (placeholder until the public layer iteration).
Route::get('/', fn () => view('welcome'))->name('home');

// --- Authentication (3.0A) ---
// `guest` keeps an already-authenticated user from re-POSTing /login and
// switching identity without logging out first.
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    // Rate limiting is per email+IP inside LoginRequest (5/min), not a global throttle.
    Route::post('/login', [LoginController::class, 'store']);
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// --- Admin (auth -> active -> permission:manage settings) ---
Route::prefix('admin')->name('admin.')
    ->middleware(['auth', 'active', 'permission:manage settings'])
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Custom-field definitions per category (3.0B).
        Route::prefix('categories/{category}/custom-fields')->name('custom-fields.')->group(function () {
            Route::get('/', [CustomFieldController::class, 'index'])->name('index');
            Route::get('/create', [CustomFieldController::class, 'create'])->name('create');
            Route::post('/', [CustomFieldController::class, 'store'])->name('store');
            Route::get('/{customField}/edit', [CustomFieldController::class, 'edit'])->name('edit');
            Route::put('/{customField}', [CustomFieldController::class, 'update'])->name('update');
            Route::delete('/{customField}', [CustomFieldController::class, 'destroy'])->name('destroy');
        });

        // Listing create/edit with custom fields (3.0C).
        Route::get('/categories/{category}/listings/create', [ListingController::class, 'create'])->name('listings.create');
        Route::post('/listings', [ListingController::class, 'store'])->name('listings.store');
        Route::get('/listings/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
        Route::put('/listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
    });

/*
| Installer — served only while the app is NOT yet installed. The
| EnsureInstalled middleware redirects here; once installed, these routes
| return 404 (see App\Http\Middleware\EnsureInstalled).
*/
Route::prefix('install')->name('install.')->group(base_path('modules/install/routes.php'));
