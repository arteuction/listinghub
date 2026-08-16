<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Taxonomy extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'context',
        'is_hierarchical',
        'allow_multiple',
        'icon_type',
        'settings',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_hierarchical' => 'boolean',
            'allow_multiple'  => 'boolean',
            'settings'        => 'array',
        ];
    }

    public function terms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class)->orderBy('sort_order');
    }

    public function rootTerms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class)
            ->whereNull('parent_id')
            ->orderBy('sort_order');
    }
}
