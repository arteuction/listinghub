<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Secure preview tokens — lets an editor share an unguessable link to
        // an unpublished ContentBlock or Page without publishing it.
        Schema::create('preview_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->morphs('previewable'); // ContentBlock, Page, TaxonomyTerm — index created by morphs()
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // Per-resource SEO metadata — one row per page, taxonomy term, or listing.
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->morphs('seoable'); // Page, TaxonomyTerm, Listing
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            // 'index,follow' | 'noindex,nofollow' | 'noindex,follow' | 'index,nofollow'
            $table->string('robots', 64)->default('index,follow');
            $table->string('canonical_path', 512)->nullable(); // relative path only
            $table->json('og')->nullable();      // {title?, description?, image_path?}
            $table->json('structured_data')->nullable(); // generated, never raw user input
            $table->timestamps();

            $table->unique(
                ['seoable_type', 'seoable_id'],
                'seo_meta_unique_resource'
            );
        });

        // Scheduled publish/unpublish queue. Jobs read this table and call the
        // appropriate publish/unpublish action at the scheduled time.
        Schema::create('scheduled_publications', function (Blueprint $table) {
            $table->id();
            $table->morphs('schedulable'); // ContentBlock, Page
            // 'publish' | 'unpublish'
            $table->string('action', 16);
            $table->timestamp('scheduled_for');
            // null = pending, datetime = processed, never updated after processing
            $table->timestamp('processed_at')->nullable();
            $table->string('result', 32)->nullable(); // 'ok' | 'skipped' | 'error'
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scheduled_for', 'processed_at'], 'scheduled_publications_pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_publications');
        Schema::dropIfExists('seo_meta');
        Schema::dropIfExists('preview_tokens');
    }
};
