<?php

declare(strict_types=1);

use App\Actions\Billing\RecordPayment;
use App\Models\PaymentEvent;
use App\Models\User;
use Illuminate\Database\QueryException;

it('rejects a duplicate (gateway, external_event_id) pair', function () {
    PaymentEvent::factory()->create([
        'gateway' => 'stripe',
        'external_event_id' => 'evt_duplicate_1',
    ]);

    PaymentEvent::factory()->create([
        'gateway' => 'stripe',
        'external_event_id' => 'evt_duplicate_1',
    ]);
})->throws(QueryException::class);

it('allows the same external id across different gateways', function () {
    PaymentEvent::factory()->create(['gateway' => 'stripe', 'external_event_id' => 'evt_shared']);
    PaymentEvent::factory()->create(['gateway' => 'paypal', 'external_event_id' => 'evt_shared']);

    expect(PaymentEvent::query()->count())->toBe(2);
});

it('assigns an idempotency_key uuid via the RecordPayment action', function () {
    $user = User::factory()->create();

    $payment = (new RecordPayment)->handle([
        'user_id' => $user->id,
        'gateway' => 'stripe',
        'amount_minor' => 1500,
        'currency' => 'USD',
    ]);

    expect($payment->idempotency_key)->not->toBeNull();
});
