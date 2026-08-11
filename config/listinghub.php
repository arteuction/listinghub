<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ListingHub application configuration
|--------------------------------------------------------------------------
|
| Platform-specific settings. Runtime-editable values live in the DB
| (App\Models\Setting); this file holds deploy-time defaults only.
|
*/

return [

    // Package version — stamped into the installer's pending marker.
    'version' => '3.2.0',

    // Listing lifecycle statuses (mirrored by App\Enums\ListingStatus).
    'listing_statuses' => ['draft', 'pending', 'published', 'suspended'],

    // New member listings require admin approval before going public.
    'moderation' => [
        'listings_require_approval' => true,
        'reviews_require_approval'  => true,
    ],

    // Variant 3 — prepared-hybrid boundary. Kept OFF: shared catalog only,
    // no tenant scopes, no UI changes. Flip on to activate future isolation.
    'multitenancy' => [
        'enabled' => false,
    ],

    // Optional license verification — HTTPS only, key sourced from .env.
    'license' => [
        'enabled' => env('LICENSE_VERIFY_ENABLED', false),
        'api_url' => env('LICENSE_API_URL'),
        'api_key' => env('LICENSE_API_KEY'),
    ],

    // Payments — default gateway; per-gateway credentials come from .env.
    'payments' => [
        'default' => env('PAYMENTS_DEFAULT', 'stripe'),
        'currency' => 'USD',
    ],
];
