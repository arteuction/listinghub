<?php

declare(strict_types=1);

use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\UpdateCategory;
use App\Actions\Content\CreateContentBlock;
use App\Actions\Content\PublishContentBlock;
use App\Actions\Content\UnpublishContentBlock;
use App\Actions\Pages\CreatePage;
use App\Enums\ContentBlockRevisionOperation;
use App\Enums\ContentBlockStatus;
use App\Enums\ContentBlockType;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Services\Install\InstallManager;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

// --- Publish / Unpublish ---

it('publish creates a revision and increments version by exactly 1', function () {
    $page = app(CreatePage::class)->handle('P', 'p');
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText,
        ['tiptap' => ['type' => 'doc', 'content' => []]],
        $page,
        actor: $this->admin,
    );

    $versionBefore = $block->version;

    $result = app(PublishContentBlock::class)->handle($block, $this->admin);

    expect($result->status)->toBe(ContentBlockStatus::Published)
        ->and($result->version)->toBe($versionBefore + 1)
        ->and($result->published_at)->not->toBeNull();

    $revision = $result->revisions()->where('operation', ContentBlockRevisionOperation::Published->value)->first();
    expect($revision)->not->toBeNull()
        ->and($revision->version)->toBe($result->version);
});

it('repeated publish does not create extra revision', function () {
    $page = app(CreatePage::class)->handle('P', 'p');
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText,
        ['tiptap' => ['type' => 'doc', 'content' => []]],
        $page,
        actor: $this->admin,
    );

    $published = app(PublishContentBlock::class)->handle($block, $this->admin);
    $versionAfterFirst = $published->version;
    $revisionCount = $published->revisions()->count();

    $again = app(PublishContentBlock::class)->handle($published, $this->admin);

    expect($again->version)->toBe($versionAfterFirst)
        ->and($again->revisions()->count())->toBe($revisionCount);
});

it('unpublish creates a revision and increments version by exactly 1', function () {
    $page = app(CreatePage::class)->handle('P', 'p');
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText,
        ['tiptap' => ['type' => 'doc', 'content' => []]],
        $page,
        actor: $this->admin,
    );

    $published = app(PublishContentBlock::class)->handle($block, $this->admin);
    $versionBefore = $published->version;

    $result = app(UnpublishContentBlock::class)->handle($published, $this->admin);

    expect($result->status)->toBe(ContentBlockStatus::Draft)
        ->and($result->version)->toBe($versionBefore + 1);

    $revision = $result->revisions()->where('operation', ContentBlockRevisionOperation::Unpublished->value)->first();
    expect($revision)->not->toBeNull();
});

it('repeated unpublish does not create extra revision', function () {
    $page = app(CreatePage::class)->handle('P', 'p');
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText,
        ['tiptap' => ['type' => 'doc', 'content' => []]],
        $page,
        actor: $this->admin,
    );

    $revisionCount = $block->revisions()->count();
    $result = app(UnpublishContentBlock::class)->handle($block, $this->admin);

    expect($result->version)->toBe($block->version)
        ->and($result->revisions()->count())->toBe($revisionCount);
});

// --- Category guards ---

it('rejects delete of category with children', function () {
    $parent = Category::factory()->create();
    Category::factory()->create(['parent_id' => $parent->id]);

    app(DeleteCategory::class)->handle($parent);
})->throws(ValidationException::class);

it('rejects delete of category with listings', function () {
    $category = Category::factory()->create();
    Listing::factory()->create(['category_id' => $category->id]);

    app(DeleteCategory::class)->handle($category);
})->throws(ValidationException::class);

it('rejects circular parent assignment', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    app(UpdateCategory::class)->handle($parent, [
        'parent_id' => $child->id,
        'name' => $parent->name,
        'slug' => $parent->slug,
        'sort_order' => $parent->sort_order,
    ]);
})->throws(ValidationException::class);

it('rejects self as own parent', function () {
    $category = Category::factory()->create();

    app(UpdateCategory::class)->handle($category, [
        'parent_id' => $category->id,
        'name' => $category->name,
        'slug' => $category->slug,
        'sort_order' => $category->sort_order,
    ]);
})->throws(ValidationException::class);

// --- Block type gating ---

it('rejects creation of unsupported block type via HTTP', function () {
    $page = app(CreatePage::class)->handle('P', 'p');

    $this->actingAs($this->admin)
        ->post(route('admin.pages.blocks.store', $page), [
            'block_type' => ContentBlockType::Video->value,
            'content' => ['url' => 'https://www.youtube.com/watch?v=test'],
        ])
        ->assertStatus(422);
});

it('allows creation of RichText via HTTP', function () {
    $page = app(CreatePage::class)->handle('P', 'p');

    $this->actingAs($this->admin)
        ->post(route('admin.pages.blocks.store', $page), [
            'block_type' => ContentBlockType::RichText->value,
            'content' => ['tiptap' => ['type' => 'doc', 'content' => []]],
        ])
        ->assertRedirect();

    expect($page->contentBlocks()->count())->toBe(1);
});

// --- storage:link exit code ---

it('InstallManager guards storage link exit code', function () {
    $source = file_get_contents(
        (new ReflectionClass(InstallManager::class))->getFileName(),
    );

    expect($source)
        ->toContain('$linkExit = Artisan::call(\'storage:link\'')
        ->and($source)->toContain('if ($linkExit !== 0)');
});
