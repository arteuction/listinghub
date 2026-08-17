<?php

declare(strict_types=1);

use App\Actions\Content\CreateContentBlock;
use App\Actions\Pages\CreatePage;
use App\Actions\Pages\PublishPage;
use App\Enums\ContentBlockStatus;
use App\Enums\ContentBlockType;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PreviewToken;
use App\Models\SeoMeta;
use App\Models\User;

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
});

it('renders a published page with its published blocks', function () {
    $page = app(CreatePage::class)->handle('За нас', 'about-us');
    app(PublishPage::class)->handle($page);

    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText,
        ['tiptap' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Добре дошли']]]]]],
        $page,
    );
    $block->update(['status' => ContentBlockStatus::Published]);

    $this->get('/pages/about-us')
        ->assertOk()
        ->assertSeeText('За нас')
        ->assertSeeText('Добре дошли');
});

it('returns 404 for a draft page', function () {
    app(CreatePage::class)->handle('Чернова', 'draft-page');

    $this->get('/pages/draft-page')->assertNotFound();
});

it('returns 404 for a non-existent slug', function () {
    $this->get('/pages/no-such-page')->assertNotFound();
});

it('hides draft blocks on a published page', function () {
    $page = app(CreatePage::class)->handle('Mixed', 'mixed-blocks');
    app(PublishPage::class)->handle($page);

    $published = app(CreateContentBlock::class)->handle(
        ContentBlockType::Announcement,
        ['text' => 'Visible announcement'],
        $page,
    );
    $published->update(['status' => ContentBlockStatus::Published]);

    app(CreateContentBlock::class)->handle(
        ContentBlockType::Announcement,
        ['text' => 'Hidden draft'],
        $page,
    );

    $this->get('/pages/mixed-blocks')
        ->assertOk()
        ->assertSeeText('Visible announcement')
        ->assertDontSeeText('Hidden draft');
});

it('renders a hero block', function () {
    $page = app(CreatePage::class)->handle('Hero Test', 'hero-test');
    app(PublishPage::class)->handle($page);

    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::Hero,
        ['title' => 'Welcome', 'subtitle' => 'Explore Bulgaria', 'cta_text' => 'Browse', 'cta_url' => '/listings'],
        $page,
    );
    $block->update(['status' => ContentBlockStatus::Published]);

    $this->get('/pages/hero-test')
        ->assertOk()
        ->assertSeeText('Welcome')
        ->assertSeeText('Explore Bulgaria')
        ->assertSeeText('Browse');
});

it('renders a FAQ block', function () {
    $page = app(CreatePage::class)->handle('FAQ Test', 'faq-test');
    app(PublishPage::class)->handle($page);

    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::Faq,
        ['title' => 'ЧЗВ', 'items' => [['question' => 'Как?', 'answer' => 'Лесно.']]],
        $page,
    );
    $block->update(['status' => ContentBlockStatus::Published]);

    $this->get('/pages/faq-test')
        ->assertOk()
        ->assertSeeText('Как?')
        ->assertSeeText('Лесно.');
});

it('applies SEO meta when present', function () {
    $page = app(CreatePage::class)->handle('SEO Page', 'seo-page');
    app(PublishPage::class)->handle($page);

    SeoMeta::query()->create([
        'seoable_type' => $page->getMorphClass(),
        'seoable_id' => $page->id,
        'meta_title' => 'Custom Title',
        'meta_description' => 'Custom description for search engines',
    ]);

    $this->get('/pages/seo-page')
        ->assertOk()
        ->assertSeeText('Custom Title');
});

it('shows empty-state when page has no blocks', function () {
    $page = app(CreatePage::class)->handle('Empty', 'empty-page');
    app(PublishPage::class)->handle($page);

    $this->get('/pages/empty-page')
        ->assertOk()
        ->assertSeeText('няма съдържание');
});

// ─── Preview ────────────────────────────────────────────────────────────────

it('renders a draft page via a valid preview token', function () {
    $page = app(CreatePage::class)->handle('Preview Draft', 'preview-draft');

    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::Announcement,
        ['text' => 'Draft content visible'],
        $page,
    );

    $token = PreviewToken::query()->create([
        'token' => 'valid-token-123',
        'previewable_type' => 'page',
        'previewable_id' => $page->id,
        'expires_at' => now()->addHour(),
    ]);

    $this->get('/pages/preview?token=valid-token-123')
        ->assertOk()
        ->assertSeeText('Преглед (draft)')
        ->assertSeeText('Draft content visible');
});

it('rejects an expired preview token', function () {
    $page = app(CreatePage::class)->handle('Expired Preview', 'expired-preview');

    PreviewToken::query()->create([
        'token' => 'expired-token',
        'previewable_type' => 'page',
        'previewable_id' => $page->id,
        'expires_at' => now()->subMinute(),
    ]);

    $this->get('/pages/preview?token=expired-token')->assertNotFound();
});

it('rejects a missing preview token', function () {
    $this->get('/pages/preview?token=nonexistent')->assertNotFound();
});

it('rejects preview without a token parameter', function () {
    $this->get('/pages/preview')->assertNotFound();
});
