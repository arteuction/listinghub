<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named, ordered group of custom fields within a category's form schema.
 * Fields with section_id = null appear before all sections ("ungrouped").
 */
final class FormSection extends Model
{
    protected $fillable = [
        'category_id',
        'title',
        'description',
        'sort_order',
        'is_collapsible',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_collapsible' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Fields assigned to this section, in order. */
    public function fields(): HasMany
    {
        return $this->hasMany(CustomField::class, 'section_id')->orderBy('sort_order');
    }
}
