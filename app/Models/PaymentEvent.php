<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentEventStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id', 'gateway', 'external_event_id', 'event_type', 'payload',
        'status', 'attempts', 'last_error', 'received_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => PaymentEventStatus::class,
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
