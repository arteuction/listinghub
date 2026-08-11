<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Typed, listing-owned custom field value. Exactly one typed column is set per
 * row (see CustomFieldType::valueColumn); an all-null row must never exist.
 */
class CustomFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_field_id', 'listing_id',
        'value_text', 'value_string', 'value_decimal', 'value_boolean',
    ];

    protected function casts(): array
    {
        return [
            'value_decimal' => 'decimal:4', // returns string, never float
            'value_boolean' => 'boolean',
        ];
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
