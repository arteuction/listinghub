<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carries the mandatory reason of a RequestChanges (Pending → Draft) move so
 * the owner can see WHY the listing came back. Cleared on resubmission — the
 * note describes the state that was returned, not the one under review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('moderation_note', 1000)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('moderation_note');
        });
    }
};
