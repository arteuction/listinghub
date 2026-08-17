<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Static pages — each owns a set of ContentBlocks via the polymorphic
        // owner columns on content_blocks. The page itself is thin; all rich
        // content lives in blocks.
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('slug')->unique();
            $table->string('title');
            // system = shipped with the platform, cannot be deleted
            $table->boolean('is_system')->default(false);
            // Only one page may be the homepage placement target
            $table->boolean('is_homepage')->default(false);
            $table->string('status', 32)->default('draft'); // draft | published
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(1000);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });

        // Navigation menus — header, footer, etc.
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('handle', 64)->unique(); // e.g. 'header', 'footer', 'footer-legal'
            $table->string('name');                  // human label shown in admin
            $table->timestamps();
        });

        // Menu items — flat or nested (parent_id for one level of nesting).
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            // Resolved href — internal path or validated https URL
            $table->string('url', 512);
            $table->boolean('open_in_new_tab')->default(false);
            $table->unsignedInteger('sort_order')->default(1000);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort_order'], 'menu_items_menu_parent_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('pages');
    }
};
