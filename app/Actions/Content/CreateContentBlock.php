<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\ContentBlockRevisionOperation;
use App\Enums\ContentBlockStatus;
use App\Enums\ContentBlockType;
use App\Models\ContentBlock;
use App\Models\User;
use App\Services\Content\RecordContentBlockRevision;
use App\Support\Content\ValidateBlockContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Creates every block as Draft and records revision 1 atomically. */
final class CreateContentBlock
{
    public function __construct(private readonly RecordContentBlockRevision $revisions) {}

    /** @param array<string, mixed> $content */
    public function handle(
        ContentBlockType $type,
        array $content,
        ?Model $owner = null,
        ?User $actor = null,
        int $sortOrder = 1000,
    ): ContentBlock {
        ValidateBlockContent::validate($type, $content);

        return DB::transaction(function () use ($type, $content, $owner, $actor, $sortOrder): ContentBlock {
            $block = ContentBlock::query()->create([
                'uuid' => (string) Str::uuid(),
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner === null ? null : (int) $owner->getKey(),
                'block_type' => $type,
                'status' => ContentBlockStatus::Draft,
                'version' => 1,
                'sort_order' => max(0, $sortOrder),
                'content' => $content,
                'created_by' => $actor?->getKey(),
                'updated_by' => $actor?->getKey(),
            ]);

            $this->revisions->record(
                $block,
                ContentBlockRevisionOperation::Created,
                $actor,
            );

            return $block;
        });
    }
}
