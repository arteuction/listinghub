<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\ContentBlockRevisionOperation;
use App\Models\ContentBlock;
use App\Models\ContentBlockRevision;
use App\Models\User;

final class RecordContentBlockRevision
{
    public function record(
        ContentBlock $block,
        ContentBlockRevisionOperation $operation,
        ?User $actor = null,
        ?string $reason = null,
    ): ContentBlockRevision {
        /** @var ContentBlockRevision $revision */
        $revision = $block->revisions()->create([
            'version' => $block->version,
            'operation' => $operation,
            'snapshot' => $block->revisionSnapshot(),
            'actor_id' => $actor?->getKey(),
            'reason' => $this->normalizeReason($reason),
        ]);

        return $revision;
    }

    private function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $reason = trim($reason);

        return $reason === '' ? null : $reason;
    }
}
