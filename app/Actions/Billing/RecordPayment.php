<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Creates a payment, guaranteeing an idempotency key. Generation lives in the
 * action rather than a model boot hook, keeping the model side-effect free.
 */
class RecordPayment
{
    /** @param array<string, mixed> $attributes */
    public function handle(array $attributes): Payment
    {
        $attributes['idempotency_key'] ??= (string) Str::uuid();

        return Payment::create($attributes);
    }
}
