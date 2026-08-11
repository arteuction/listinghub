<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 3.4.1 — Bulgaria Map & Geo Search.
 *
 * Composite indexes on (latitude, longitude) speed up the map endpoint's
 * bbox range scan on both the listings and settlements tables (the latter
 * backs the coordinate fallback for listings with no explicit point).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->index(['latitude', 'longitude'], 'listings_lat_lng_index');
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->index(['latitude', 'longitude'], 'settlements_lat_lng_index');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_lat_lng_index');
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->dropIndex('settlements_lat_lng_index');
        });
    }
};
