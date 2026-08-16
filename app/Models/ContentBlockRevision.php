<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentBlockRevisionOperation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $version
 * @property ContentBlockRevisionOperation $operation
 * @property array<string, mixed> $snapshot
 * @property int|null $actor_id
 */
class ContentBlockRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'content_block_id', 'version', 'operation', 'snapshot', 'actor_id', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'operation' => ContentBlockRevisionOperation::class,
            'snapshot' => 'array',
            'actor_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Content block revisions are immutable.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Content block revisions are immutable.');
        });
    }

    /** @return BelongsTo<ContentBlock, $this> */
    public function contentBlock(): BelongsTo
    {
        return $this->belongsTo(ContentBlock::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
