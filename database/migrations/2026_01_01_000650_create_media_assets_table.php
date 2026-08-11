<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One polymorphic gallery table for every owner (listings, products,
 * reviews). Replaces the separate per-entity image tables — a single code
 * path and a single index instead of duplicated schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->morphs('mediable'); // listing | product | review | ...
            $table->string('collection', 64)->default('gallery'); // gallery | cover | document
            $table->string('disk', 64)->default('public');
            $table->string('path');
            $table->string('file_name')->nullable();
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('alt_text')->nullable();
            // Sparse ordering base step (App\Support\SparseOrder::STEP).
            $table->unsignedInteger('sort_order')->default(1000);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['mediable_type', 'mediable_id', 'collection', 'sort_order'],
                'media_collection_order'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
