<?php

declare(strict_types=1);

use App\Actions\Content\CreateContentBlock;
use App\Actions\Content\ReorderContentBlocks;
use App\Actions\Pages\CreatePage;
use App\Actions\Pages\PublishPage;
use App\Actions\Pages\SyncMenuItems;
use App\Actions\Pages\UnpublishPage;
use App\Actions\Pages\UpdatePage;
use App\Enums\ContentBlockType;
use App\Enums\PageStatus;
use App\Models\Menu;
use App\Models\Page;
use Database\Seeders\SystemPageSeeder;
use Illuminate\Support\Str;

// ─── Pages ──────────────────────────────────────────────────────────────────

it('creates a page as draft and rounds sort_order', function () {
    $page = app(CreatePage::class)->handle('За нас', 'about', sortOrder: 500);

    expect($page->title)->toBe('За нас')
        ->and($page->slug)->toBe('about')
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->sort_order)->toBe(500)
        ->and($page->is_system)->toBeFalse()
        ->and($page->is_homepage)->toBeFalse();
});

it('publishes a page and stamps published_at once', function () {
    $page = app(CreatePage::class)->handle('Контакти', 'contact');

    app(PublishPage::class)->handle($page);
    $page->refresh();
    $first = $page->published_at;

    expect($page->status)->toBe(PageStatus::Published)
        ->and($first)->not->toBeNull();

    // Second publish must not overwrite published_at
    app(PublishPage::class)->handle($page);
    $page->refresh();
    expect($page->published_at->eq($first))->toBeTrue();
});

it('cannot unpublish a system page', function () {
    $page = Page::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'terms', 'title' => 'Terms', 'is_system' => true,
        'is_homepage' => false, 'status' => PageStatus::Published,
        'sort_order' => 1000,
    ]);

    expect(fn () => app(UnpublishPage::class)->handle($page))
        ->toThrow(InvalidArgumentException::class, 'System pages');
});

it('only one page holds is_homepage at a time', function () {
    $a = app(CreatePage::class)->handle('Home A', 'home-a', isHomepage: true);
    $b = app(CreatePage::class)->handle('Home B', 'home-b', isHomepage: true);

    expect($a->fresh()->is_homepage)->toBeFalse()
        ->and($b->fresh()->is_homepage)->toBeTrue();
});

it('rejects changing slug on a system page', function () {
    $page = Page::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'privacy', 'title' => 'Privacy', 'is_system' => true,
        'is_homepage' => false, 'status' => PageStatus::Draft, 'sort_order' => 1000,
    ]);

    expect(fn () => app(UpdatePage::class)->handle($page, ['slug' => 'new-slug']))
        ->toThrow(InvalidArgumentException::class, 'immutable');
});

it('rejects unknown fields in UpdatePage', function () {
    $page = app(CreatePage::class)->handle('Test', 'test-slug');

    expect(fn () => app(UpdatePage::class)->handle($page, ['status' => 'published']))
        ->toThrow(InvalidArgumentException::class);
});

// ─── Block reordering ────────────────────────────────────────────────────────

it('reorders content blocks within a page owner', function () {
    $page = app(CreatePage::class)->handle('Reorder Test', 'reorder-test');

    $a = app(CreateContentBlock::class)->handle(ContentBlockType::RichText, ['tiptap' => ['type' => 'doc', 'content' => []]], $page, sortOrder: 1000);
    $b = app(CreateContentBlock::class)->handle(ContentBlockType::RichText, ['tiptap' => ['type' => 'doc', 'content' => []]], $page, sortOrder: 2000);
    $c = app(CreateContentBlock::class)->handle(ContentBlockType::RichText, ['tiptap' => ['type' => 'doc', 'content' => []]], $page, sortOrder: 3000);

    // Reverse the order
    app(ReorderContentBlocks::class)->handle([$c->id, $b->id, $a->id], $page);

    [$first, $second, $third] = $page->contentBlocks()->pluck('id')->all();

    expect($first)->toBe($c->id)
        ->and($second)->toBe($b->id)
        ->and($third)->toBe($a->id);
});

it('rejects a block ID that does not belong to the owner', function () {
    $pageA = app(CreatePage::class)->handle('Page A', 'page-a');
    $pageB = app(CreatePage::class)->handle('Page B', 'page-b');

    $blockOnA = app(CreateContentBlock::class)->handle(ContentBlockType::RichText, ['tiptap' => ['type' => 'doc', 'content' => []]], $pageA);
    $blockOnB = app(CreateContentBlock::class)->handle(ContentBlockType::RichText, ['tiptap' => ['type' => 'doc', 'content' => []]], $pageB);

    expect(fn () => app(ReorderContentBlocks::class)->handle([$blockOnA->id, $blockOnB->id], $pageA))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects duplicate IDs in the reorder list', function () {
    $page = app(CreatePage::class)->handle('Dup Test', 'dup-test');
    $block = app(CreateContentBlock::class)->handle(ContentBlockType::RichText, ['tiptap' => ['type' => 'doc', 'content' => []]], $page);

    expect(fn () => app(ReorderContentBlocks::class)->handle([$block->id, $block->id], $page))
        ->toThrow(InvalidArgumentException::class, 'duplicate');
});

it('no-ops a reorder with an empty list', function () {
    $page = app(CreatePage::class)->handle('Empty', 'empty-reorder');
    // Should complete without exception
    app(ReorderContentBlocks::class)->handle([], $page);
    expect(true)->toBeTrue();
});

// ─── Menus ──────────────────────────────────────────────────────────────────

it('syncs menu items replacing the previous set', function () {
    $menu = Menu::query()->create(['handle' => 'header', 'name' => 'Header']);

    app(SyncMenuItems::class)->handle($menu, [
        ['label' => 'Home',     'url' => '/'],
        ['label' => 'About',    'url' => '/about'],
        ['label' => 'External', 'url' => 'https://example.com', 'open_in_new_tab' => true],
    ]);

    $items = $menu->fresh()->items;
    expect($items)->toHaveCount(3)
        ->and($items[2]->open_in_new_tab)->toBeTrue();

    // Replace with a single item
    app(SyncMenuItems::class)->handle($menu, [['label' => 'Home', 'url' => '/']]);
    expect($menu->fresh()->items)->toHaveCount(1);
});

it('supports one level of nesting via parent_index', function () {
    $menu = Menu::query()->create(['handle' => 'footer', 'name' => 'Footer']);

    app(SyncMenuItems::class)->handle($menu, [
        ['label' => 'Company',  'url' => '#'],
        ['label' => 'About',    'url' => '/about',   'parent_index' => 0],
        ['label' => 'Contact',  'url' => '/contact', 'parent_index' => 0],
    ]);

    $root = $menu->rootItems()->first();
    expect($root->label)->toBe('Company')
        ->and($root->children)->toHaveCount(2);
});

it('rejects a javascript url in a menu item', function () {
    $menu = Menu::query()->create(['handle' => 'test-menu', 'name' => 'Test']);

    expect(fn () => app(SyncMenuItems::class)->handle($menu, [
        ['label' => 'Evil', 'url' => 'javascript:alert(1)'],
    ]))->toThrow(InvalidArgumentException::class, 'scheme');
});

it('rejects a parent_index that is not before the current item', function () {
    $menu = Menu::query()->create(['handle' => 'bad-parent', 'name' => 'Bad Parent']);

    expect(fn () => app(SyncMenuItems::class)->handle($menu, [
        ['label' => 'A', 'url' => '/', 'parent_index' => 1],
        ['label' => 'B', 'url' => '/b'],
    ]))->toThrow(InvalidArgumentException::class, 'parent_index');
});

it('rejects a menu item with an empty label', function () {
    $menu = Menu::query()->create(['handle' => 'empty-label', 'name' => 'Empty']);

    expect(fn () => app(SyncMenuItems::class)->handle($menu, [
        ['label' => '   ', 'url' => '/'],
    ]))->toThrow(InvalidArgumentException::class, 'label');
});

// ─── System page seeder ──────────────────────────────────────────────────────

it('system page seeder is idempotent', function () {
    $seeder = new SystemPageSeeder;
    $seeder->run();
    $seeder->run();

    $count = Page::query()->where('is_system', true)->count();
    expect($count)->toBe(6);

    $menus = Menu::query()->count();
    expect($menus)->toBe(3);
});
