<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\ContentBlockRevisionOperation;
use App\Enums\ContentBlockType;
use App\Exceptions\ContentBlockConflict;
use App\Models\ContentBlock;
use App\Models\User;
use App\Services\Content\RecordContentBlockRevision;
use App\Support\Tiptap\TiptapValidator;
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

            if (array_key_exists('content', $normalized)
                && isset($normalized['content']['tiptap'])
                && is_array($normalized['content']['tiptap'])
            ) {
                (new TiptapValidator)->validate($normalized['content']['tiptap']);
            }

            $locked->fill($normalized);

            // A form re-submit with identical data is not a new historical state.
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
