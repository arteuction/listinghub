<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingHourException extends Model
{
    use HasFactory;

    protected $fillable = ['listing_id', 'date', 'opens_at', 'closes_at', 'is_closed', 'note'];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_closed' => 'boolean'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
