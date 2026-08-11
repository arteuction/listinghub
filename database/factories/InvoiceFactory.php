<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->numberBetween(500, 50000);

        return [
            'user_id' => User::factory(),
            'subscription_id' => null,
            'number' => 'INV-'.strtoupper(Str::random(10)),
            'gateway' => null,
            'gateway_invoice_id' => null,
            'amount_due_minor' => $amount,
            'amount_paid_minor' => 0,
            'amount_refunded_minor' => 0,
            'currency' => 'USD',
            'status' => InvoiceStatus::Unpaid->value,
        ];
    }
}
