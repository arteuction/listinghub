<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product features render in the order the owner wrote them; without a sort
 * key the display order would be id order, which reshuffles on every re-sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
