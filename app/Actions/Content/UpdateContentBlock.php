<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\ContentBlockRevisionOperation;
use App\Enums\ContentBlockType;
use App\Exceptions\ContentBlockConflict;
use App\Models\ContentBlock;
use App\Models\User;
use App\Services\Content\RecordContentBlockRevision;
use App\Support\Content\ValidateBlockContent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Updates presentation data under an optimistic version check.
 * Status changes receive their own closed transition action in 3.6.0D.
 */
final class UpdateContentBlock
{
    private const ALLOWED_CHANGES = ['block_type', 'content', 'sort_order'];

    /** @param array<string, mixed> $data */
    private static function sortKeysRecursive(array $data): array
    {
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = array_is_list($value)
                    ? array_map(fn ($v) => is_array($v) ? self::sortKeysRecursive($v) : $v, $value)
                    : self::sortKeysRecursive($value);
            }
        }

        return $data;
    }

    public function __construct(private readonly RecordContentBlockRevision $revisions) {}

    /** @param array<string, mixed> $changes */
    public function handle(
        ContentBlock $block,
        array $changes,
        int $expectedVersion,
        ?User $actor = null,
        ?string $reason = null,
    ): ContentBlock {
        $unknown = array_diff(array_keys($changes), self::ALLOWED_CHANGES);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unsupported content block fields: '.implode(', ', $unknown));
        }

        return DB::transaction(function () use ($block, $changes, $expectedVersion, $actor, $reason): ContentBlock {
            /** @var ContentBlock $locked */
            $locked = ContentBlock::query()->lockForUpdate()->findOrFail($block->getKey());

            if ($locked->version !== $expectedVersion) {
                throw ContentBlockConflict::staleVersion($expectedVersion, $locked->version);
            }

            $normalized = Arr::only($changes, self::ALLOWED_CHANGES);

            if (array_key_exists('block_type', $normalized)) {
                $normalized['block_type'] = $normalized['block_type'] instanceof ContentBlockType
                    ? $normalized['block_type']
                    : ContentBlockType::from((string) $normalized['block_type']);
            }

            if (array_key_exists('sort_order', $normalized)) {
                $normalized['sort_order'] = max(0, (int) $normalized['sort_order']);
            }

            if (array_key_exists('content', $normalized) && ! is_array($normalized['content'])) {
                throw new InvalidArgumentException('Content block content must be a JSON object.');
            }

            $locked->fill($normalized);

            $finalType = $locked->block_type;
            $finalContent = $locked->content;
            if (is_array($finalContent)) {
                ValidateBlockContent::validate($finalType, $finalContent);
            }

            // MySQL normalises JSON key order alphabetically while SQLite
            // preserves insertion order, making isDirty() unreliable for the
            // `content` JSON column. Sort keys on both sides before comparing.
            if ($locked->isDirty('content')) {
                $original = self::sortKeysRecursive((array) $locked->getOriginal('content'));
                $current = self::sortKeysRecursive((array) $locked->content);

                if ($original === $current) {
                    $locked->content = $locked->getOriginal('content');
                }
            }

            if (! $locked->isDirty()) {
                return $locked;
            }

            $locked->version++;
            $locked->updated_by = $actor?->getKey();
            $locked->save();

            $this->revisions->record(
                $locked,
                ContentBlockRevisionOperation::Updated,
                $actor,
                $reason,
            );

            return $locked;
        });
    }
}
