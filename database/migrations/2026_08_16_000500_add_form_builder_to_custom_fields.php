<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 3.6.1 Form Experience Builder additions:
 *  - form_sections — named, ordered groups within a category schema
 *  - custom_fields additions: section_id, placeholder, help_text
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(1000);
            $table->boolean('is_collapsible')->default(false);
            $table->timestamps();

            $table->index(['category_id', 'sort_order'], 'form_sections_category_order');
        });

        Schema::table('custom_fields', function (Blueprint $table) {
            $table->foreignId('section_id')
                ->nullable()
                ->after('category_id')
                ->constrained('form_sections')
                ->nullOnDelete();
            $table->string('placeholder', 255)->nullable()->after('label');
            $table->string('help_text', 512)->nullable()->after('placeholder');
        });
    }

    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropColumn(['section_id', 'placeholder', 'help_text']);
        });
        Schema::dropIfExists('form_sections');
    }
};
