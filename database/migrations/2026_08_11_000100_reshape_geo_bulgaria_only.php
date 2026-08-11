<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 3.2.3 — Bulgaria Geo Foundation.
 *
 * Replaces the generic country/state/city hierarchy with the precise
 * Bulgarian administrative structure:
 *
 *   Bulgaria (single country, iso2=BG)
 *   └── Region / Област        (28 regions)
 *       └── Municipality / Община
 *           └── Settlement / Населено място  (cities, towns, villages)
 *
 * Rename path: states → regions, cities → municipalities, new settlements.
 * Listings are re-pointed from city_id → settlement_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------ //
        // 1. Regions (бивши states)                                           //
        // ------------------------------------------------------------------ //
        Schema::rename('states', 'regions');

        Schema::table('regions', function (Blueprint $table) {
            // Drop old FK to countries; we keep country_id but rename nothing —
            // the single BG record will always be referenced.
            $table->string('code', 10)->nullable()->after('name')
                  ->comment('NUTS-3 or official BG region code');
            $table->decimal('latitude', 10, 7)->nullable()->after('code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->json('boundary')->nullable()->after('longitude')
                  ->comment('GeoJSON Polygon/MultiPolygon for the region boundary');
        });

        // ------------------------------------------------------------------ //
        // 2. Municipalities (бивши cities → renamed)                          //
        // ------------------------------------------------------------------ //
        Schema::rename('cities', 'municipalities');

        Schema::table('municipalities', function (Blueprint $table) {
            // Rename FK column state_id → region_id.
            // Drop old index/FK first, then rename, re-add constraint.
            $table->dropForeign(['state_id']);
            $table->renameColumn('state_id', 'region_id');
        });

        Schema::table('municipalities', function (Blueprint $table) {
            $table->foreign('region_id')->references('id')->on('regions')->cascadeOnDelete();
            $table->string('code', 10)->nullable()->after('name')
                  ->comment('EKATTE municipality code');
            $table->json('boundary')->nullable()->after('longitude')
                  ->comment('GeoJSON Polygon/MultiPolygon for the municipality boundary');
        });

        // ------------------------------------------------------------------ //
        // 3. Settlements (new — finest geo grain)                             //
        // ------------------------------------------------------------------ //
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('ekatte', 10)->nullable()->unique()
                  ->comment('Official EKATTE code from the Bulgarian National Register');
            $table->string('type', 20)->default('city')
                  ->comment('city | town | village | quarter');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['municipality_id', 'slug']);
        });

        // ------------------------------------------------------------------ //
        // 4. Listings: swap city_id → settlement_id                           //
        // ------------------------------------------------------------------ //
        Schema::table('listings', function (Blueprint $table) {
            // Add new column first (nullable so existing rows survive).
            $table->foreignId('settlement_id')->nullable()
                  ->after('city_id')
                  ->constrained()->nullOnDelete();
        });

        // Migrate existing city_id values: create a stub settlement per city.
        // In practice listings table is empty at this migration point,
        // but the SQL is idempotent for safety.
        DB::statement('
            UPDATE listings l
            JOIN municipalities m ON m.id = l.city_id
            SET l.settlement_id = (
                SELECT s.id FROM settlements s
                WHERE s.municipality_id = m.id
                LIMIT 1
            )
            WHERE l.city_id IS NOT NULL
        ');

        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn('city_id');
            // Update composite index.
            $table->dropIndex(['city_id', 'status']);
            $table->index(['settlement_id', 'status']);
        });

        // ------------------------------------------------------------------ //
        // 5. Users: drop generic country_id (BG is implicit)                  //
        // ------------------------------------------------------------------ //
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');
        });
    }

    public function down(): void
    {
        // Restore users.country_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
        });

        // Restore listings.city_id
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['settlement_id', 'status']);
            $table->dropForeign(['settlement_id']);
            $table->dropColumn('settlement_id');
            $table->foreignId('city_id')->nullable()->constrained('municipalities')->nullOnDelete();
            $table->index(['city_id', 'status']);
        });

        Schema::dropIfExists('settlements');

        Schema::table('municipalities', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn(['code', 'boundary']);
            $table->renameColumn('region_id', 'state_id');
        });

        Schema::table('municipalities', function (Blueprint $table) {
            $table->foreign('state_id')->references('id')->on('regions')->cascadeOnDelete();
        });

        Schema::rename('municipalities', 'cities');

        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn(['code', 'latitude', 'longitude', 'boundary']);
        });

        Schema::rename('regions', 'states');
    }
};
