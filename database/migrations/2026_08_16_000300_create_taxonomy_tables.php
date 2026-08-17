<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vocabulary definitions: listing-categories, amenities-*, blog-tags, etc.
        Schema::create('taxonomies', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique(); // machine name, immutable after terms exist
            $table->string('name');               // human label
            // listings | products | pages | articles — determines which models can receive terms
            $table->string('context', 64)->default('listings');
            // Hierarchical = tree (categories); non-hierarchical = flat (tags, amenities)
            $table->boolean('is_hierarchical')->default(true);
            // Whether a termable may hold multiple terms from this taxonomy
            $table->boolean('allow_multiple')->default(false);
            // icon_type: 'none' | 'emoji' | 'svg' | 'upload'
            $table->string('icon_type', 16)->default('none');
            $table->json('settings')->nullable(); // future extensibility, never arbitrary code
            $table->timestamps();
        });

        // Terms: individual entries within a taxonomy vocabulary.
        Schema::create('taxonomy_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('taxonomy_terms')->restrictOnDelete();
            $table->string('slug', 128);
            $table->string('name');
            $table->string('icon')->nullable();   // emoji, SVG name, or media path depending on icon_type
            $table->text('description')->nullable(); // Tiptap JSON (stored as text, rendered via @tiptap)
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(1000);
            // draft | published | archived | hidden
            $table->string('status', 32)->default('published');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Slug must be unique within a taxonomy
            $table->unique(['taxonomy_id', 'slug'], 'taxonomy_terms_taxonomy_slug');
            // Tree walk by parent
            $table->index(['taxonomy_id', 'parent_id', 'sort_order'], 'taxonomy_terms_tree_order');
            // Status filter
            $table->index(['taxonomy_id', 'status', 'sort_order'], 'taxonomy_terms_status_order');
        });

        // Polymorphic pivot: attaches terms to any model.
        Schema::create('taxonomy_termables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxonomy_term_id')->constrained()->cascadeOnDelete();
            $table->morphs('termable'); // listing, product, article, etc.
            $table->timestamps();

            $table->unique(
                ['taxonomy_term_id', 'termable_id', 'termable_type'],
                'taxonomy_termables_unique'
            );
        });

        // Compatibility bridge: map listings.category_id → taxonomy_terms
        // A NULL here means the category has not been migrated to the taxonomy yet.
        // This column is removed once listings.category_id is fully replaced.
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('taxonomy_term_id')
                ->nullable()
                ->after('is_active')
                ->constrained('taxonomy_terms')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['taxonomy_term_id']);
            $table->dropColumn('taxonomy_term_id');
        });
        Schema::dropIfExists('taxonomy_termables');
        Schema::dropIfExists('taxonomy_terms');
        Schema::dropIfExists('taxonomies');
    }
};
