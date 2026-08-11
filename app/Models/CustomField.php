<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
