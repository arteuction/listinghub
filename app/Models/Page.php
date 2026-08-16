<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A static page whose visible content is composed entirely of ContentBlocks.
 * System pages (is_system = true) are seeded and cannot be deleted.
 */
final class Page extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'status', 'sort_order'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'uuid',
        'slug',
        'title',
        'is_system',
        'is_homepage',
        'status',
        'published_at',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status'       => PageStatus::class,
            'is_system'    => 'boolean',
            'is_homepage'  => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** ContentBlocks owned by this page, ordered for rendering. */
    public function contentBlocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class, 'owner_id')
            ->where('owner_type', $this->getMorphClass())
            ->orderBy('sort_order');
    }

    public function getMorphClass(): string
    {
        return 'page';
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published;
    }
}
