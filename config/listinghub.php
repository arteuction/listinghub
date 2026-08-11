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
    'version' => '3.5.0',

    // Listing lifecycle statuses (mirrored by App\Enums\ListingStatus).
    'listing_statuses' => ['draft', 'pending', 'published', 'suspended'],

    // New member listings require admin approval before going public.
    'moderation' => [
        'listings_require_approval' => true,
        'reviews_require_approval' => true,
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

    // Interactive map (3.4.1 — Bulgaria Map & Geo Search).
    // MAP_TILE_URL: raster tile template, fine for dev/staging with OSM.
    //   OSM tiles have no SLA — switch to a hosted provider for production:
    //   MapTiler (https://maptiler.com), Stadia Maps, Protomaps, etc.
    // For vector-tile styles set MAP_TILE_URL to a MapLibre style JSON URL.
    'map' => [
        'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'max_features' => (int) env('MAP_MAX_FEATURES', 1000),
        // Bulgaria center [lng, lat] and default zoom.
        'center' => [25.5, 42.7],
        'default_zoom' => 7,
        // Minimum zoom — stops the user zooming out past a whole-country view.
        'min_zoom' => 6,
        // Hard geographic envelope — the map cannot pan outside Bulgaria.
        'bounds' => [[22.3, 41.2], [28.7, 44.3]],
    ],
];
