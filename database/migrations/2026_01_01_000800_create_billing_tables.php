<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified billing schema with integer money and idempotent gateway events.
 * A single payments + payment_events pair replaces the original's three
 * per-gateway webhook-log tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->string('gateway', 32)->nullable();
            $table->string('gateway_invoice_id')->nullable();
            $table->unsignedBigInteger('amount_due_minor');
            $table->unsignedBigInteger('amount_paid_minor')->default(0);
            $table->unsignedBigInteger('amount_refunded_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('status', 32)->default('unpaid')->index(); // App\Enums\InvoiceStatus
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'gateway_invoice_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 32); // stripe | paypal | razorpay | bank_transfer
            $table->string('gateway_payment_id')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('status', 32)->default('pending')->index(); // App\Enums\PaymentStatus
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'gateway_payment_id']);
        });

        // Idempotent webhook / gateway event log with observability fields.
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 32);
            $table->string('external_event_id');
            $table->string('event_type');
            $table->longText('payload');
            $table->string('status', 32)->default('received')->index(); // App\Enums\PaymentEventStatus
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            // Idempotency key: one row per (gateway, external event id).
            $table->unique(['gateway', 'external_event_id']);
            $table->index(['gateway', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
    }
};
