<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete(); // Variant 3 boundary
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // Money as integer minor units (e.g. cents) + ISO 4217 currency.
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('interval', 16)->default('month'); // App\Enums\BillingInterval
            $table->unsignedInteger('listing_limit')->nullable(); // null = unlimited
            $table->unsignedInteger('gallery_limit')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete(); // Variant 3 boundary
            $table->string('status', 32)->default('pending')->index(); // App\Enums\SubscriptionStatus
            // Immutable snapshot of the plan at purchase time — later plan edits
            // never rewrite subscription history.
            $table->string('plan_slug_snapshot');
            $table->string('plan_name_snapshot');
            $table->unsignedBigInteger('price_minor_snapshot');
            $table->char('currency_snapshot', 3);
            $table->string('interval_snapshot', 16);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
