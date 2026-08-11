<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Attributes whose runtime type comes from casts(). Larastan resolves model
 * properties from annotations, not from the casts array, so without these the
 * flag columns read as undefined and $type as a plain string.
 *
 * @property CustomFieldType $type
 * @property array<int, array{value: string, label?: string}>|null $options
 * @property bool $is_required
 * @property bool $searchable
 * @property bool $filterable
 * @property bool $sortable
 */
class CustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'label', 'key', 'type', 'options', 'is_required', 'sort_order',
        'searchable', 'filterable', 'sortable',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'searchable' => 'boolean',
            'filterable' => 'boolean',
            'sortable' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
