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
 *
 * NOTE ON CONSTRAINT NAMES: MySQL keeps the ORIGINAL constraint name when a
 * table is renamed, while Laravel's `dropForeign([$column])` derives the name
 * from the CURRENT table name. Every constraint dropped after a rename is
 * therefore named explicitly with its legacy name.
 */
return new class extends Migration
{
    /**
     * SQLite has no notion of a named foreign-key constraint to drop: it
     * recreates the whole table (copy/rename) whenever a column or FK is
     * altered, which drops the old constraint as a side effect. Laravel's
     * SQLiteGrammar therefore throws on an explicit dropForeign() call — so
     * on SQLite we skip it and let the later column/rename operation do the
     * equivalent work; on MySQL the named drop is required as documented
     * above (renamed tables keep the original constraint name).
     */
    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    public function up(): void
    {
        // ------------------------------------------------------------------ //
        // 1. Regions (former states)                                          //
        // ------------------------------------------------------------------ //
        Schema::rename('states', 'regions');

        Schema::table('regions', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->after('name')
                ->comment('NUTS-3 or official BG region code');
            $table->decimal('latitude', 10, 7)->nullable()->after('code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->json('boundary')->nullable()->after('longitude')
                ->comment('GeoJSON Polygon/MultiPolygon for the region boundary');
        });

        // ------------------------------------------------------------------ //
        // 2. Municipalities (former cities)                                   //
        // ------------------------------------------------------------------ //
        Schema::rename('cities', 'municipalities');

        // Legacy name — the constraint was created while the table was `cities`.
        if (! $this->isSqlite()) {
            Schema::table('municipalities', function (Blueprint $table) {
                $table->dropForeign('cities_state_id_foreign');
            });
        }

        Schema::table('municipalities', function (Blueprint $table) {
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
                ->comment('town | village | monastery — the NSI EKATTE type set');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['municipality_id', 'slug']);
        });

        // ------------------------------------------------------------------ //
        // 4. Listings: swap city_id → settlement_id                           //
        // ------------------------------------------------------------------ //
        // A municipality does not map onto any single settlement, so there is no
        // correct automatic conversion: existing listings keep a NULL settlement
        // and must be re-assigned against the BG dataset before publication.
        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('settlement_id')->nullable()->after('city_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropIndex(['city_id', 'status']);
            $table->dropColumn('city_id');
        });

        Schema::table('listings', function (Blueprint $table) {
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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['settlement_id', 'status']);
            $table->dropForeign(['settlement_id']);
            $table->dropColumn('settlement_id');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('plan_id')
                ->constrained('municipalities')->nullOnDelete();
            $table->index(['city_id', 'status']);
        });

        Schema::dropIfExists('settlements');

        Schema::table('municipalities', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn(['code', 'boundary']);
        });

        Schema::table('municipalities', function (Blueprint $table) {
            $table->renameColumn('region_id', 'state_id');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn(['code', 'latitude', 'longitude', 'boundary']);
        });

        // Both tables must carry their original names before the legacy
        // cities → states foreign key can be recreated.
        Schema::rename('regions', 'states');
        Schema::rename('municipalities', 'cities');

        Schema::table('cities', function (Blueprint $table) {
            $table->foreign('state_id')->references('id')->on('states')->cascadeOnDelete();
        });
    }
};
