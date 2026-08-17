<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ContentBlock is the versioned foundation for 3.6.0 Admin Experience Studio.
 *
 * A block may be global (owner columns NULL) or belong to any Eloquent model.
 * Revisions are append-only full snapshots. Rolling back therefore creates a
 * new version instead of rewriting or deleting audit history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->nullableMorphs('owner');
            $table->string('block_type', 64)->index();
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('sort_order')->default(1000);
            $table->json('content');
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['owner_type', 'owner_id', 'status', 'sort_order'],
                'content_blocks_owner_status_order'
            );
        });

        Schema::create('content_block_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_block_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('operation', 32);
            $table->json('snapshot');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['content_block_id', 'version']);
            $table->index(['content_block_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_block_revisions');
        Schema::dropIfExists('content_blocks');
    }
};
