<?php

declare(strict_types=1);

use App\Actions\Content\CreateContentBlock;
use App\Actions\Content\DeleteContentBlock;
use App\Actions\Content\RollbackContentBlock;
use App\Actions\Content\UpdateContentBlock;
use App\Enums\ContentBlockRevisionOperation;
use App\Enums\ContentBlockStatus;
use App\Enums\ContentBlockType;
use App\Exceptions\ContentBlockConflict;
use App\Models\Category;
use App\Models\ContentBlock;
use App\Models\ContentBlockRevision;
use App\Models\User;

function tiptapDoc(string $text): array
{
    return [
        'tiptap' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $text]],
            ]],
        ],
    ];
}

function heroContent(string $title, string $subtitle = ''): array
{
    return array_filter([
        'title' => $title,
        'subtitle' => $subtitle ?: null,
    ]);
}

function faqContent(string $title, array $items): array
{
    return ['title' => $title, 'items' => $items];
}

it('creates a draft block and its first full snapshot atomically', function () {
    $actor = User::factory()->create();

    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::Hero,
        heroContent('Добре дошли'),
        actor: $actor,
        sortOrder: 2000,
    );

    $revision = $block->revisions()->sole();

    expect($block->uuid)->not->toBeEmpty()
        ->and($block->getRouteKeyName())->toBe('uuid')
        ->and($block->status)->toBe(ContentBlockStatus::Draft)
        ->and($block->version)->toBe(1)
        ->and($block->owner_type)->toBeNull()
        ->and($block->owner_id)->toBeNull()
        ->and($block->created_by)->toBe($actor->id)
        ->and($revision->operation)->toBe(ContentBlockRevisionOperation::Created)
        ->and($revision->version)->toBe(1)
        ->and($revision->actor_id)->toBe($actor->id)
        ->and($revision->snapshot['uuid'])->toBe($block->uuid)
        ->and($revision->snapshot['content'])->toEqual(heroContent('Добре дошли'))
        ->and($revision->snapshot['schema_version'])->toBe(ContentBlock::SNAPSHOT_SCHEMA_VERSION);
});

it('supports a polymorphic owner without requiring one for global blocks', function () {
    $category = Category::factory()->create();

    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::CategoryGrid,
        ['title' => 'Подкатегории'],
        owner: $category,
    );

    expect($block->owner_type)->toBe(Category::class)
        ->and($block->owner_id)->toBe($category->id)
        ->and($block->owner->is($category))->toBeTrue();
});

it('increments the version and stores a full snapshot for each real update', function () {
    $actor = User::factory()->create();
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText,
        tiptapDoc('Версия едно'),
        actor: $actor,
    );

    $updated = app(UpdateContentBlock::class)->handle(
        $block,
        [
            'block_type' => ContentBlockType::ImageText,
            'content' => ['title' => 'Версия две', 'tiptap' => ['type' => 'doc', 'content' => []], 'layout' => 'left'],
            'sort_order' => 3000,
        ],
        expectedVersion: 1,
        actor: $actor,
        reason: 'Редакция на началната секция',
    );

    $revisions = $updated->revisions()->reorder('version')->get();

    expect($updated->version)->toBe(2)
        ->and($updated->block_type)->toBe(ContentBlockType::ImageText)
        ->and($updated->content)->toHaveKey('title', 'Версия две')
        ->and($revisions)->toHaveCount(2)
        ->and($revisions[1]->operation)->toBe(ContentBlockRevisionOperation::Updated)
        ->and($revisions[1]->reason)->toBe('Редакция на началната секция')
        ->and($revisions[1]->snapshot['version'])->toBe(2)
        ->and($revisions[1]->snapshot['sort_order'])->toBe(3000);

    // An identical form re-submit is not a historical state.
    $same = app(UpdateContentBlock::class)->handle(
        $updated,
        [
            'block_type' => ContentBlockType::ImageText,
            'content' => ['title' => 'Версия две', 'tiptap' => ['type' => 'doc', 'content' => []], 'layout' => 'left'],
            'sort_order' => 3000,
        ],
        expectedVersion: 2,
        actor: $actor,
    );

    expect($same->version)->toBe(2)
        ->and($same->revisions()->count())->toBe(2);
});

it('rejects a stale editor without changing data or writing a revision', function () {
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText,
        tiptapDoc('Начало'),
    );

    app(UpdateContentBlock::class)->handle(
        $block,
        ['content' => tiptapDoc('Запазено от първия редактор')],
        expectedVersion: 1,
    );

    expect(fn () => app(UpdateContentBlock::class)->handle(
        $block,
        ['content' => tiptapDoc('Стара форма')],
        expectedVersion: 1,
    ))->toThrow(ContentBlockConflict::class, 'expected 1, current version is 2');

    $fresh = ContentBlock::query()->findOrFail($block->id);

    expect($fresh->content)->toEqual(tiptapDoc('Запазено от първия редактор'))
        ->and($fresh->version)->toBe(2)
        ->and($fresh->revisions()->count())->toBe(2);
});

it('rolls back by appending a new version and preserves the complete history', function () {
    $actor = User::factory()->create();
    $owner = Category::factory()->create();
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::Hero,
        heroContent('Оригинал'),
        owner: $owner,
        actor: $actor,
    );
    $uuid = $block->uuid;

    $versionTwo = app(UpdateContentBlock::class)->handle(
        $block,
        ['content' => heroContent('Втора версия')],
        expectedVersion: 1,
        actor: $actor,
    );
    $versionThree = app(UpdateContentBlock::class)->handle(
        $versionTwo,
        ['block_type' => ContentBlockType::Announcement, 'content' => ['text' => 'Трета версия']],
        expectedVersion: 2,
        actor: $actor,
    );

    $rolledBack = app(RollbackContentBlock::class)->handle(
        $versionThree,
        targetVersion: 1,
        expectedVersion: 3,
        actor: $actor,
    );

    $revisions = $rolledBack->revisions()->reorder('version')->get();

    expect($rolledBack->version)->toBe(4)
        ->and($rolledBack->uuid)->toBe($uuid)
        ->and($rolledBack->owner_type)->toBe(Category::class)
        ->and($rolledBack->owner_id)->toBe($owner->id)
        ->and($rolledBack->block_type)->toBe(ContentBlockType::Hero)
        ->and($rolledBack->content)->toEqual(heroContent('Оригинал'))
        ->and($revisions)->toHaveCount(4)
        ->and($revisions->pluck('version')->all())->toBe([1, 2, 3, 4])
        ->and($revisions[3]->operation)->toBe(ContentBlockRevisionOperation::RolledBack)
        ->and($revisions[3]->reason)->toBe('Rollback to version 1')
        ->and($revisions[3]->snapshot['version'])->toBe(4);
});

it('does not allow a revision row to be changed or deleted', function () {
    $block = ContentBlock::factory()->create();
    $revision = $block->revisions()->sole();

    expect(function () use ($revision): void {
        $revision->reason = 'Опит за промяна';
        $revision->save();
    })->toThrow(LogicException::class, 'immutable');

    $freshRevision = ContentBlockRevision::query()->findOrFail($revision->id);

    expect(fn () => $freshRevision->delete())
        ->toThrow(LogicException::class, 'immutable');
});

it('rejects rollback to the current or a future version', function (int $target) {
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::Hero,
        heroContent('Текст'),
    );

    expect(fn () => app(RollbackContentBlock::class)->handle(
        $block,
        targetVersion: $target,
        expectedVersion: 1,
    ))->toThrow(ContentBlockConflict::class);
})->with([1, 2]);

it('deletes a freshly created block without a version collision', function () {
    $actor = User::factory()->create();
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText,
        tiptapDoc('Ще бъде изтрит'),
        actor: $actor,
    );

    expect($block->version)->toBe(1)
        ->and($block->revisions()->count())->toBe(1);

    app(DeleteContentBlock::class)->handle($block, $actor);

    $fresh = ContentBlock::withTrashed()->findOrFail($block->id);

    expect($fresh->deleted_at)->not->toBeNull()
        ->and($fresh->version)->toBe(2);

    $revisions = $fresh->revisions()->reorder('version')->get();

    expect($revisions)->toHaveCount(2)
        ->and($revisions->pluck('version')->all())->toBe([1, 2])
        ->and($revisions[0]->operation)->toBe(ContentBlockRevisionOperation::Created)
        ->and($revisions[1]->operation)->toBe(ContentBlockRevisionOperation::Deleted)
        ->and($revisions[1]->actor_id)->toBe($actor->id);
});

it('deletes an updated block with monotonically increasing revision versions', function () {
    $actor = User::factory()->create();
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::Hero,
        heroContent('Версия 1'),
        actor: $actor,
    );

    $block = app(UpdateContentBlock::class)->handle(
        $block,
        ['content' => heroContent('Версия 2')],
        expectedVersion: 1,
        actor: $actor,
    );

    $block = app(UpdateContentBlock::class)->handle(
        $block,
        ['content' => heroContent('Версия 3')],
        expectedVersion: 2,
        actor: $actor,
    );

    expect($block->version)->toBe(3);

    app(DeleteContentBlock::class)->handle($block, $actor);

    $fresh = ContentBlock::withTrashed()->findOrFail($block->id);
    $versions = $fresh->revisions()->reorder('version')->pluck('version')->all();

    expect($fresh->version)->toBe(4)
        ->and($versions)->toBe([1, 2, 3, 4]);
});

it('records a full snapshot in the delete revision', function () {
    $actor = User::factory()->create();
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::Faq,
        faqContent('ЧЗВ', [['question' => 'Въпрос?', 'answer' => 'Отговор.']]),
        actor: $actor,
    );

    app(DeleteContentBlock::class)->handle($block, $actor);

    $deleteRevision = ContentBlock::withTrashed()->findOrFail($block->id)
        ->revisions()
        ->where('operation', ContentBlockRevisionOperation::Deleted)
        ->sole();

    expect($deleteRevision->snapshot)->toHaveKey('uuid', $block->uuid)
        ->and($deleteRevision->snapshot)->toHaveKey('schema_version', ContentBlock::SNAPSHOT_SCHEMA_VERSION)
        ->and($deleteRevision->snapshot['content'])->toHaveKey('title', 'ЧЗВ');
});
