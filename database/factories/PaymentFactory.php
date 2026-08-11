<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'invoice_id' => null,
            'subscription_id' => null,
            'gateway' => 'stripe',
            'gateway_payment_id' => 'pi_'.Str::random(16),
            'idempotency_key' => (string) Str::uuid(),
            'amount_minor' => fake()->numberBetween(500, 50000),
            'refunded_minor' => 0,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending->value,
        ];
    }
}
