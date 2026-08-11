<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Variant 3 — prepared-hybrid boundary.
 *
 * A minimal organizations table exists so that the nullable organization_id
 * columns on users/listings/products/plans/subscriptions have a real FK
 * target. It is intentionally unused by the shared-catalog UI and logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
