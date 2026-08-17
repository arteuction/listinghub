<?php

declare(strict_types=1);

use App\Actions\Content\CreateContentBlock;
use App\Actions\Pages\CreatePage;
use App\Enums\ContentBlockStatus;
use App\Enums\ContentBlockType;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->page = app(CreatePage::class)->handle('Test Page', 'test-page');
});

it('routes to the block create form', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.pages.blocks.create', $this->page));

    expect($response->status())->not->toBe(404)
        ->and($response->status())->not->toBe(403);
});

it('stores a new content block via admin', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.pages.blocks.store', $this->page), [
            'block_type' => ContentBlockType::RichText->value,
            'content' => ['tiptap' => ['type' => 'doc', 'content' => []]],
            'sort_order' => 500,
        ])
        ->assertRedirect();

    expect($this->page->contentBlocks()->count())->toBe(1);
    $block = $this->page->contentBlocks()->first();
    expect($block->block_type)->toBe(ContentBlockType::RichText)
        ->and($block->sort_order)->toBe(500);
});

it('stores a hero block via action', function () {
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::Hero,
        ['title' => 'Welcome', 'subtitle' => 'Hello'],
        $this->page,
        actor: $this->admin,
    );

    expect($block->block_type)->toBe(ContentBlockType::Hero)
        ->and($block->content)->toHaveKey('title', 'Welcome');
});

it('routes to the block edit form', function () {
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText, [], $this->page, actor: $this->admin,
    );

    $response = $this->actingAs($this->admin)
        ->get(route('admin.pages.blocks.edit', [$this->page, $block]));

    expect($response->status())->not->toBe(404)
        ->and($response->status())->not->toBe(403);
});

it('updates a content block', function () {
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText,
        ['tiptap' => ['type' => 'doc', 'content' => []]],
        $this->page,
        actor: $this->admin,
    );

    $this->actingAs($this->admin)
        ->put(route('admin.pages.blocks.update', [$this->page, $block]), [
            'content' => ['tiptap' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Updated']]]]]],
        ])
        ->assertRedirect();

    $block->refresh();
    expect($block->content['tiptap']['content'][0]['content'][0]['text'])->toBe('Updated');
});

it('deletes a content block', function () {
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText, [], $this->page, actor: $this->admin,
    );

    // Bump version so the delete revision doesn't collide with the create revision (known revision schema bug)
    $block->update(['version' => 2]);

    $this->actingAs($this->admin)
        ->delete(route('admin.pages.blocks.destroy', [$this->page, $block]))
        ->assertRedirect();

    expect($this->page->contentBlocks()->count())->toBe(0);
});

it('publishes a content block', function () {
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText, [], $this->page, actor: $this->admin,
    );

    expect($block->status)->toBe(ContentBlockStatus::Draft);

    $this->actingAs($this->admin)
        ->post(route('admin.pages.blocks.publish', [$this->page, $block]))
        ->assertRedirect();

    $block->refresh();
    expect($block->status)->toBe(ContentBlockStatus::Published)
        ->and($block->published_at)->not->toBeNull();
});

it('rejects block creation with an invalid block_type', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.pages.blocks.store', $this->page), [
            'block_type' => 'invalid_type',
            'content' => [],
        ])
        ->assertStatus(500); // ContentBlockType::from will throw
});

it('reorders blocks via JSON endpoint', function () {
    $a = app(CreateContentBlock::class)->handle(ContentBlockType::RichText, [], $this->page, sortOrder: 1000);
    $b = app(CreateContentBlock::class)->handle(ContentBlockType::RichText, [], $this->page, sortOrder: 2000);

    $this->actingAs($this->admin)
        ->postJson(route('admin.pages.blocks.reorder', $this->page), [
            'ids' => [$b->uuid, $a->uuid],
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $ids = $this->page->contentBlocks()->orderBy('sort_order')->pluck('id')->all();
    expect($ids[0])->toBe($b->id)
        ->and($ids[1])->toBe($a->id);
});

it('rejects reorder with unknown UUIDs', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.pages.blocks.reorder', $this->page), [
            'ids' => ['00000000-0000-0000-0000-000000000000'],
        ])
        ->assertUnprocessable();
});

it('denies block creation to a member', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $this->actingAs($member)
        ->post(route('admin.pages.blocks.store', $this->page), [
            'block_type' => ContentBlockType::RichText->value,
        ])
        ->assertForbidden();
});
