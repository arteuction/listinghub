<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\ContentBlockRevisionOperation;
use App\Models\ContentBlock;
use App\Models\User;
use App\Services\Content\RecordContentBlockRevision;
use Illuminate\Support\Facades\DB;

final class DeleteContentBlock
{
    public function __construct(private readonly RecordContentBlockRevision $revisions) {}

    public function handle(ContentBlock $block, ?User $actor = null): void
    {
        DB::transaction(function () use ($block, $actor): void {
            $this->revisions->record(
                $block,
                ContentBlockRevisionOperation::Deleted,
                $actor,
            );

            $block->updated_by = $actor?->getKey();
            $block->save();
            $block->delete(); // soft delete
        });
    }
}
