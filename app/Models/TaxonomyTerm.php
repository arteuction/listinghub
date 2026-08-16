<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaxonomyTermStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class TaxonomyTerm extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'parent_id', 'sort_order', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('admin');
    }

    protected $fillable = [
        'taxonomy_id',
        'parent_id',
        'slug',
        'name',
        'icon',
        'description',
        'image_path',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TaxonomyTermStatus::class,
        ];
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** ContentBlocks owned by this taxonomy term (category landing pages). */
    public function contentBlocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class, 'owner_id')
            ->where('owner_type', $this->getMorphClass())
            ->orderBy('sort_order');
    }

    public function getMorphClass(): string
    {
        return 'taxonomy_term';
    }

    /** Listings that have this term attached. */
    public function listings(): MorphToMany
    {
        return $this->morphedByMany(Listing::class, 'termable', 'taxonomy_termables');
    }

    public function isPublished(): bool
    {
        return $this->status === TaxonomyTermStatus::Published;
    }
}
