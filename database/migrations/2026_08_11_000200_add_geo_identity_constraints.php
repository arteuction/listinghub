<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotes the official codes to real identities.
 *
 * The NSI dataset keys every level by an authoritative code — region code,
 * municipality code, EKATTE for settlements — while names and slugs are
 * attributes that can change. Seeding against slugs is unsafe: seven pairs of
 * settlements share a name inside one municipality (гара Бов and село Бов, the
 * town and the village of Костенец, …), so a slug-keyed upsert silently
 * collapses them.
 *
 * These unique indexes make the codes usable as upsert conflict targets and
 * turn any future collision into a hard failure instead of lost rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->unique('code');
            $table->unique('slug');
        });

        Schema::table('municipalities', function (Blueprint $table) {
            $table->unique('code');
            $table->unique(['region_id', 'slug']);
        });

        Schema::table('settlements', function (Blueprint $table) {
            // The public catalog resolves ?settlement=<slug> within a
            // municipality, so the pair must be unambiguous.
            $table->unique(['municipality_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropUnique(['municipality_id', 'slug']);
        });

        Schema::table('municipalities', function (Blueprint $table) {
            $table->dropUnique(['region_id', 'slug']);
            $table->dropUnique(['code']);
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropUnique(['code']);
        });
    }
};
