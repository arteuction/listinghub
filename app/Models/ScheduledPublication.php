<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class ScheduledPublication extends Model
{
    protected $fillable = [
        'schedulable_type',
        'schedulable_id',
        'action',
        'scheduled_for',
        'processed_at',
        'result',
        'error_message',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'processed_at'  => 'datetime',
        ];
    }

    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->processed_at === null;
    }
}
