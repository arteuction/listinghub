<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ClaimDocumentController;
use App\Http\Controllers\Admin\CustomFieldController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Admin\ListingController;
use App\Http\Controllers\Admin\ListingHoursController as AdminListingHoursController;
use App\Http\Controllers\Admin\MenuController as AdminMenuController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\PageContentBlockController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SeoMetaController as AdminSeoMetaController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TaxonomyController as AdminTaxonomyController;
use App\Http\Controllers\Admin\UserController;
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

use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\SettlementSearchController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Member\FavoriteController;
use App\Http\Controllers\Member\LeadController as MemberLeadController;
use App\Http\Controllers\Member\ListingController as MemberListingController;
use App\Http\Controllers\Member\ListingHoursController;
use App\Http\Controllers\Member\ListingMediaController;
use App\Http\Controllers\Member\ProductController;
use App\Http\Controllers\Member\ProductMediaController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Site\ClaimController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\LeadController;
use App\Http\Controllers\Site\ListingController as SiteListingController;
use App\Http\Controllers\Site\PageController as SitePageController;
use App\Http\Controllers\Site\ProductController as SiteProductController;
use App\Http\Controllers\Site\ReviewController;
use App\Http\Controllers\Site\SettlementLookupController;
use App\Http\Controllers\Site\SitemapController;
use Illuminate\Support\Facades\Route;

// --- Public catalog (3.3.0) ---
// Bulgaria-only marketplace. Location narrowing below region level is done
// with query parameters (?municipality=…&settlement=…) against the canonical
// region page, so every listing has exactly one indexable browse path.
// --- Static pages (public, 3.6.2) ---
// Preview is public-but-token-gated; the token itself is the auth.
Route::get('/pages/preview', [SitePageController::class, 'preview'])->name('pages.preview');
Route::get('/pages/{slug}', [SitePageController::class, 'show'])->name('pages.show');

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/listings', [SiteListingController::class, 'index'])->name('listings.index');
Route::get('/categories/{category:slug}', [SiteListingController::class, 'index'])->name('categories.show');
Route::get('/regions/{region:slug}', [SiteListingController::class, 'index'])->name('regions.show');
Route::get('/listings/{listing:slug}', [SiteListingController::class, 'show'])->name('listings.show');
// Public product detail (3.5.3) — resolved inside the listing's scope, so a
// slug from a different listing 404s (see Site\ProductController).
Route::get('/listings/{listing:slug}/products/{productSlug}', [SiteProductController::class, 'show'])->name('listings.products.show');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// --- Enquiries (3.5.0) ---
// Anonymous by design (see CaptureLead): requiring an account to contact a
// business would suppress most genuine enquiries, so the throttle — not
// identity — is the abuse control here.
Route::post('/listings/{listing:slug}/contact', [LeadController::class, 'store'])
    ->middleware('throttle:10,1')->name('listings.leads.store');

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

        // Gallery. Ownership is checked by ListingPolicy AND the asset's morph
        // pair, so an owner cannot act on another listing's asset id.
        Route::post('/listings/{listing}/media', [ListingMediaController::class, 'store'])
            ->middleware('throttle:30,1')->name('listings.media.store');
        Route::post('/listings/{listing}/media/{asset}/cover', [ListingMediaController::class, 'cover'])
            ->name('listings.media.cover');
        Route::delete('/listings/{listing}/media/{asset}', [ListingMediaController::class, 'destroy'])
            ->name('listings.media.destroy');
        Route::delete('/listings/{listing}', [MemberListingController::class, 'destroy'])->name('listings.destroy');

        // Products (3.4.2). Managing products is part of managing the listing
        // — every action authorises against the parent listing, not a
        // separate resource-level route gate (see ProductController).
        Route::prefix('listings/{listing}/products')->name('listings.products.')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('/create', [ProductController::class, 'create'])->name('create');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
            Route::put('/{product}', [ProductController::class, 'update'])->name('update');
            Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
            // Product gallery (3.5.3) — same verified pipeline as listing media.
            Route::post('/{product}/media', [ProductMediaController::class, 'store'])->name('media.store');
            Route::delete('/{product}/media/{asset}', [ProductMediaController::class, 'destroy'])->name('media.destroy');
        });

        // Working hours + date exceptions (3.4.2). The weekly schedule is a
        // single full-replacement PUT (SyncListingHours), not a per-day CRUD.
        Route::prefix('listings/{listing}/hours')->name('listings.hours.')->group(function () {
            Route::get('/', [ListingHoursController::class, 'edit'])->name('edit');
            Route::put('/', [ListingHoursController::class, 'update'])->name('update');
            Route::post('/exceptions', [ListingHoursController::class, 'storeException'])->name('exceptions.store');
            Route::delete('/exceptions/{exception}', [ListingHoursController::class, 'destroyException'])->name('exceptions.destroy');
        });

        Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
        Route::post('/favorites/{listing}', [FavoriteController::class, 'store'])->name('favorites.store');
        Route::delete('/favorites/{listing}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');

        // Enquiry inbox for a listing the member owns (3.5.0).
        Route::get('/listings/{listing}/leads', [MemberLeadController::class, 'index'])->name('listings.leads.index');
        Route::post('/listings/{listing}/leads/{lead}/read', [MemberLeadController::class, 'markRead'])
            ->name('listings.leads.read');
    });

    // --- Reviews and ownership claims (3.5.0) ---
    // Both require a signed-in, active account: a review moves a public score
    // and a claim can transfer a listing, so neither may be anonymous.
    Route::post('/listings/{listing:slug}/reviews', [ReviewController::class, 'store'])
        ->middleware('throttle:10,1')->name('listings.reviews.store');
    Route::post('/listings/{listing:slug}/claim', [ClaimController::class, 'store'])
        ->middleware('throttle:5,1')->name('listings.claims.store');
});

// Public geo reference for the cascading location picker (see
// SettlementLookupController: scoped per region, never the whole table).
Route::get('/regions/{region:slug}/settlements', SettlementLookupController::class)
    ->name('regions.settlements');

// --- Map API (3.4.1) ---
// Both endpoints are public (no auth required), rate-limited, and cacheable.
// The map endpoint reuses the same PublicListingQuery filter stack as the
// catalog page, guaranteeing that map and list always show the same set.
Route::prefix('api/catalog')->name('api.catalog.')->group(function () {
    Route::get('/map', MapController::class)
        ->middleware('throttle:120,1')
        ->name('map');

    Route::get('/settlements', SettlementSearchController::class)
        ->middleware('throttle:60,1')
        ->name('settlements');
});

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

        // Category CRUD (3.5.1). Destructive edges are guarded in the
        // controller — a category with children or listings is not deletable.
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

        // Every listing regardless of status, plus the status transitions
        // (3.5.1). The public catalog can only ever show published rows.
        Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
        Route::post('/listings/{listing}/transition', [ListingController::class, 'transition'])->name('listings.transition');

        // Enquiries across every listing (3.5.1).
        Route::get('/leads', [AdminLeadController::class, 'index'])->name('leads.index');
        Route::post('/leads/{lead}/read', [AdminLeadController::class, 'markRead'])->name('leads.read');
        Route::delete('/leads/{lead}', [AdminLeadController::class, 'destroy'])->name('leads.destroy');

        // Runtime settings (3.5.1) — only those that change behaviour.
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

        // Listing create/edit with custom fields (3.0C).
        Route::get('/categories/{category}/listings/create', [ListingController::class, 'create'])->name('listings.create');
        Route::post('/listings', [ListingController::class, 'store'])->name('listings.store');
        Route::get('/listings/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
        Route::put('/listings/{listing}', [ListingController::class, 'update'])->name('listings.update');

        // Products for any listing (3.4.2).
        Route::prefix('listings/{listing}/products')->name('listings.products.')->group(function () {
            Route::get('/', [AdminProductController::class, 'index'])->name('index');
            Route::get('/create', [AdminProductController::class, 'create'])->name('create');
            Route::post('/', [AdminProductController::class, 'store'])->name('store');
            Route::get('/{product}/edit', [AdminProductController::class, 'edit'])->name('edit');
            Route::put('/{product}', [AdminProductController::class, 'update'])->name('update');
            Route::delete('/{product}', [AdminProductController::class, 'destroy'])->name('destroy');
        });

        // Working hours + date exceptions for any listing (3.4.2).
        Route::prefix('listings/{listing}/hours')->name('listings.hours.')->group(function () {
            Route::get('/', [AdminListingHoursController::class, 'edit'])->name('edit');
            Route::put('/', [AdminListingHoursController::class, 'update'])->name('update');
            Route::post('/exceptions', [AdminListingHoursController::class, 'storeException'])->name('exceptions.store');
            Route::delete('/exceptions/{exception}', [AdminListingHoursController::class, 'destroyException'])->name('exceptions.destroy');
        });

        // Moderation queue — pending listings, reviews and claims (3.5.0).
        Route::get('/moderation', [ModerationController::class, 'index'])->name('moderation.index');
        Route::post('/moderation/reviews/{review}', [ModerationController::class, 'decideReview'])->name('moderation.reviews.decide');
        Route::post('/moderation/claims/{claim}', [ModerationController::class, 'decideClaim'])->name('moderation.claims.decide');
        // Proof-of-ownership document (3.5.2) — private disk, staff-only,
        // served as an attachment; there is deliberately NO public URL.
        Route::get('/claims/{claim}/document', [ClaimDocumentController::class, 'download'])->name('claims.document');

        // User management (3.5.0). Password changes are deliberately absent —
        // see UserController.
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

        // --- Pages + ContentBlocks (3.6.2) ---
        Route::prefix('pages')->name('pages.')->group(function () {
            Route::get('/', [AdminPageController::class, 'index'])->name('index');
            Route::get('/create', [AdminPageController::class, 'create'])->name('create');
            Route::post('/', [AdminPageController::class, 'store'])->name('store');
            Route::get('/{page}/edit', [AdminPageController::class, 'edit'])->name('edit');
            Route::put('/{page}', [AdminPageController::class, 'update'])->name('update');
            Route::delete('/{page}', [AdminPageController::class, 'destroy'])->name('destroy');
            Route::post('/{page}/publish', [AdminPageController::class, 'publish'])->name('publish');
            Route::post('/{page}/unpublish', [AdminPageController::class, 'unpublish'])->name('unpublish');
            Route::post('/{page}/preview', [AdminPageController::class, 'preview'])->name('preview');

            // Content blocks within a page — block must belong to the page
            Route::prefix('/{page}/blocks')->name('blocks.')->group(function () {
                Route::get('/', [AdminPageController::class, 'blocks'])->name('index');
                Route::get('/create', [PageContentBlockController::class, 'create'])->name('create');
                Route::post('/', [PageContentBlockController::class, 'store'])->name('store');
                Route::get('/{block}/edit', [PageContentBlockController::class, 'edit'])->name('edit');
                Route::put('/{block}', [PageContentBlockController::class, 'update'])->name('update');
                Route::delete('/{block}', [PageContentBlockController::class, 'destroy'])->name('destroy');
                Route::post('/{block}/publish', [PageContentBlockController::class, 'publish'])->name('publish');
                Route::post('/{block}/unpublish', [PageContentBlockController::class, 'unpublish'])->name('unpublish');
                Route::post('/reorder', [PageContentBlockController::class, 'reorder'])->name('reorder');
            })->scopeBindings();

            // SEO per page
            Route::get('/{page}/seo', [AdminSeoMetaController::class, 'editForPage'])->name('seo.edit');
            Route::put('/{page}/seo', [AdminSeoMetaController::class, 'updateForPage'])->name('seo.update');
        });

        // Shortcut used from page edit: admin.pages.blocks
        Route::get('/pages/{page}/content-blocks', [AdminPageController::class, 'blocks'])->name('pages.blocks');

        // --- Menus (3.6.2) ---
        Route::prefix('menus')->name('menus.')->group(function () {
            Route::get('/', [AdminMenuController::class, 'index'])->name('index');
            Route::get('/{menu}/edit', [AdminMenuController::class, 'edit'])->name('edit');
            Route::put('/{menu}', [AdminMenuController::class, 'update'])->name('update');
        });

        // --- Taxonomy (3.6.2) ---
        Route::prefix('taxonomy')->name('taxonomy.')->group(function () {
            Route::get('/', [AdminTaxonomyController::class, 'index'])->name('index');
            Route::get('/{taxonomy}/terms', [AdminTaxonomyController::class, 'terms'])->name('terms');
            Route::get('/{taxonomy}/terms/create', [AdminTaxonomyController::class, 'createTerm'])->name('terms.create');
            Route::post('/{taxonomy}/terms', [AdminTaxonomyController::class, 'storeTerm'])->name('terms.store');
            Route::get('/{taxonomy}/terms/{term}/edit', [AdminTaxonomyController::class, 'editTerm'])->name('terms.edit');
            Route::put('/{taxonomy}/terms/{term}', [AdminTaxonomyController::class, 'updateTerm'])->name('terms.update');
            Route::post('/{taxonomy}/terms/{term}/move', [AdminTaxonomyController::class, 'moveTerm'])->name('terms.move');
            Route::delete('/{taxonomy}/terms/{term}', [AdminTaxonomyController::class, 'destroyTerm'])->name('terms.destroy');
        });

        // SEO for taxonomy terms (standalone — reached from term edit)
        Route::get('/taxonomy/terms/{term}/seo', [AdminSeoMetaController::class, 'editForTerm'])->name('taxonomy.terms.seo.edit');
        Route::put('/taxonomy/terms/{term}/seo', [AdminSeoMetaController::class, 'updateForTerm'])->name('taxonomy.terms.seo.update');
    });

/*
| Installer — served only while the app is NOT yet installed. The
| EnsureInstalled middleware redirects here; once installed, these routes
| return 404 (see App\Http\Middleware\EnsureInstalled).
*/
Route::prefix('install')->name('install.')->group(base_path('modules/install/routes.php'));
