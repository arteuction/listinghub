<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentEventStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_id' => null,
            'gateway' => 'stripe',
            'external_event_id' => 'evt_'.Str::random(16),
            'event_type' => 'payment_intent.succeeded',
            // Cast on the model is 'array' — pass a raw array, not a JSON string.
            'payload' => ['id' => 'evt_'.Str::random(8)],
            'status' => PaymentEventStatus::Received->value,
            'attempts' => 0,
            'received_at' => now(),
        ];
    }
}
