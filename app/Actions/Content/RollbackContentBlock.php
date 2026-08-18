<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\ContentBlockRevisionOperation;
use App\Enums\ContentBlockStatus;
use App\Enums\ContentBlockType;
use App\Exceptions\ContentBlockConflict;
use App\Models\ContentBlock;
use App\Models\ContentBlockRevision;
use App\Models\User;
use App\Services\Content\RecordContentBlockRevision;
use App\Support\Content\ValidateBlockContent;
use Illuminate\Support\Facades\DB;

/** Rollback is append-only: it restores data into a brand-new version. */
final class RollbackContentBlock
{
    public function __construct(private readonly RecordContentBlockRevision $revisions) {}

    public function handle(
        ContentBlock $block,
        int $targetVersion,
        int $expectedVersion,
        ?User $actor = null,
        ?string $reason = null,
    ): ContentBlock {
        return DB::transaction(function () use (
            $block,
            $targetVersion,
            $expectedVersion,
            $actor,
            $reason,
        ): ContentBlock {
            /** @var ContentBlock $locked */
            $locked = ContentBlock::query()->lockForUpdate()->findOrFail($block->getKey());

            if ($locked->version !== $expectedVersion) {
                throw ContentBlockConflict::staleVersion($expectedVersion, $locked->version);
            }

            if ($targetVersion >= $locked->version) {
                throw ContentBlockConflict::revisionNotHistorical($targetVersion, $locked->version);
            }

            /** @var ContentBlockRevision $target */
            $target = $locked->revisions()
                ->where('version', $targetVersion)
                ->firstOrFail();

            $snapshot = $target->snapshot;
            $this->guardSnapshot($snapshot, $targetVersion, $locked);

            $restoredType = ContentBlockType::from((string) $snapshot['block_type']);
            ValidateBlockContent::validate($restoredType, $snapshot['content']);

            $locked->fill([
                'block_type' => ContentBlockType::from((string) $snapshot['block_type']),
                'status' => ContentBlockStatus::from((string) $snapshot['status']),
                'sort_order' => (int) $snapshot['sort_order'],
                'content' => $snapshot['content'],
                'scheduled_for' => $snapshot['scheduled_for'],
                'published_at' => $snapshot['published_at'],
            ]);
            $locked->version++;
            $locked->updated_by = $actor?->getKey();
            $locked->save();

            $this->revisions->record(
                $locked,
                ContentBlockRevisionOperation::RolledBack,
                $actor,
                $reason ?? "Rollback to version {$targetVersion}",
            );

            return $locked;
        });
    }

    /** @param array<string, mixed> $snapshot */
    private function guardSnapshot(array $snapshot, int $version, ContentBlock $block): void
    {
        $required = [
            'schema_version', 'uuid', 'owner_type', 'owner_id', 'block_type',
            'status', 'version', 'sort_order', 'content', 'scheduled_for',
            'published_at',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $snapshot)) {
                throw ContentBlockConflict::invalidSnapshot($version);
            }
        }

        $snapshotOwnerId = $snapshot['owner_id'] === null ? null : (int) $snapshot['owner_id'];

        if ((int) $snapshot['schema_version'] !== ContentBlock::SNAPSHOT_SCHEMA_VERSION
            || (string) $snapshot['uuid'] !== $block->uuid
            || $snapshot['owner_type'] !== $block->owner_type
            || $snapshotOwnerId !== $block->owner_id
            || (int) $snapshot['version'] !== $version
            || ContentBlockType::tryFrom((string) $snapshot['block_type']) === null
            || ContentBlockStatus::tryFrom((string) $snapshot['status']) === null
            || ! is_numeric($snapshot['sort_order'])
            || ! is_array($snapshot['content'])
            || ! $this->isNullableDate($snapshot['scheduled_for'])
            || ! $this->isNullableDate($snapshot['published_at'])) {
            throw ContentBlockConflict::invalidSnapshot($version);
        }
    }

    private function isNullableDate(mixed $value): bool
    {
        return $value === null || is_string($value);
    }
}
