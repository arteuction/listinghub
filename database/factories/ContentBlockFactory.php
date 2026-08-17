<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentBlockRevisionOperation;
use App\Enums\ContentBlockStatus;
use App\Enums\ContentBlockType;
use App\Models\ContentBlock;
use App\Services\Content\RecordContentBlockRevision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContentBlock> */
class ContentBlockFactory extends Factory
{
    protected $model = ContentBlock::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'owner_type' => null,
            'owner_id' => null,
            'block_type' => ContentBlockType::RichText,
            'status' => ContentBlockStatus::Draft,
            'version' => 1,
            'sort_order' => 1000,
            'content' => [
                'document' => [
                    'type' => 'doc',
                    'content' => [],
                ],
            ],
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ContentBlock $block): void {
            if (! $block->revisions()->exists()) {
                app(RecordContentBlockRevision::class)->record(
                    $block,
                    ContentBlockRevisionOperation::Created,
                );
            }
        });
    }
}
