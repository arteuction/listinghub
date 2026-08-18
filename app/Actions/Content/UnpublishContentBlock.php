<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\ContentBlockRevisionOperation;
use App\Enums\ContentBlockStatus;
use App\Models\ContentBlock;
use App\Models\User;
use App\Services\Content\RecordContentBlockRevision;
use Illuminate\Support\Facades\DB;

final class UnpublishContentBlock
{
    public function __construct(private readonly RecordContentBlockRevision $revisions) {}

    public function handle(ContentBlock $block, ?User $actor = null): ContentBlock
    {
        return DB::transaction(function () use ($block, $actor): ContentBlock {
            /** @var ContentBlock $locked */
            $locked = ContentBlock::query()->lockForUpdate()->findOrFail($block->getKey());

            if ($locked->status === ContentBlockStatus::Draft) {
                return $locked;
            }

            $locked->status = ContentBlockStatus::Draft;
            $locked->version++;
            $locked->updated_by = $actor?->getKey();
            $locked->save();

            $this->revisions->record($locked, ContentBlockRevisionOperation::Unpublished, $actor);

            return $locked;
        });
    }
}
