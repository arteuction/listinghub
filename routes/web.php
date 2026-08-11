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
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Member\FavoriteController;
use App\Http\Controllers\Member\ListingController as MemberListingController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\ListingController as SiteListingController;
use App\Http\Controllers\Site\SettlementLookupController;
use App\Http\Controllers\Site\SitemapController;
use Illuminate\Support\Facades\Route;

// --- Public catalog (3.3.0) ---
// Bulgaria-only marketplace. Location narrowing below region level is done
// with query parameters (?municipality=…&settlement=…) against the canonical
// region page, so every listing has exactly one indexable browse path.
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/listings', [SiteListingController::class, 'index'])->name('listings.index');
Route::get('/categories/{category:slug}', [SiteListingController::class, 'index'])->name('categories.show');
Route::get('/regions/{region:slug}', [SiteListingController::class, 'index'])->name('regions.show');
Route::get('/listings/{listing:slug}', [SiteListingController::class, 'show'])->name('listings.show');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// --- Authentication (3.0A) ---
// `guest` keeps an already-authenticated user from re-POSTing /login and
// switching identity without logging out first.
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    // Rate limiting is per email+IP inside LoginRequest (5/min), not a global throttle.
    Route::post('/login', [LoginController::class, 'store']);

    // --- Registration (3.4.0) ---
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    // --- Password reset (3.4.0) ---
    // The broker's own per-address throttle is in config/auth.php; these limits
    // cap how hard one client can hammer the endpoints regardless of address.
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')->name('password.store');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// --- Email verification + member account (3.4.0) ---
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    // `signed` proves the link came from us; the id/hash pair is checked by
    // EmailVerificationRequest before the action body runs.
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')->name('verification.send');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // --- Member area: own listings and favourites (3.4.0) ---
    // Ownership is enforced by ListingPolicy inside the controller, not by the
    // route: the route only proves the caller is an active, signed-in user.
    Route::prefix('member')->name('member.')->group(function () {
        Route::get('/listings', [MemberListingController::class, 'index'])->name('listings.index');
        Route::get('/listings/create', [MemberListingController::class, 'create'])->name('listings.create');
        Route::post('/listings', [MemberListingController::class, 'store'])->name('listings.store');
        Route::get('/listings/{listing}/edit', [MemberListingController::class, 'edit'])->name('listings.edit');
        Route::put('/listings/{listing}', [MemberListingController::class, 'update'])->name('listings.update');
        Route::post('/listings/{listing}/submit', [MemberListingController::class, 'submit'])->name('listings.submit');
        Route::delete('/listings/{listing}', [MemberListingController::class, 'destroy'])->name('listings.destroy');

        Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
        Route::post('/favorites/{listing}', [FavoriteController::class, 'store'])->name('favorites.store');
        Route::delete('/favorites/{listing}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
    });
});

// Public geo reference for the cascading location picker (see
// SettlementLookupController: scoped per region, never the whole table).
Route::get('/regions/{region:slug}/settlements', SettlementLookupController::class)
    ->name('regions.settlements');

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
