<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentBlockStatus;
use App\Enums\ContentBlockType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property ContentBlockType $block_type
 * @property ContentBlockStatus $status
 * @property int $version
 * @property int $sort_order
 * @property array<string, mixed> $content
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class ContentBlock extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const SNAPSHOT_SCHEMA_VERSION = 1;

    protected $fillable = [
        'uuid', 'owner_type', 'owner_id', 'block_type', 'status', 'version',
        'sort_order', 'content', 'scheduled_for', 'published_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'block_type' => ContentBlockType::class,
            'status' => ContentBlockStatus::class,
            'version' => 'integer',
            'sort_order' => 'integer',
            'content' => 'array',
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<ContentBlockRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ContentBlockRevision::class)->orderByDesc('version');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Full logical snapshot used for simple, deterministic rollback.
     * Immutable identity (uuid and owner) is recorded for audit, but rollback
     * deliberately restores only mutable fields.
     *
     * @return array<string, mixed>
     */
    public function revisionSnapshot(): array
    {
        return [
            'schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
            'uuid' => $this->uuid,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'block_type' => $this->block_type->value,
            'status' => $this->status->value,
            'version' => $this->version,
            'sort_order' => $this->sort_order,
            'content' => $this->content,
            'scheduled_for' => $this->scheduled_for?->toISOString(),
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
