<?php

declare(strict_types=1);

use App\Actions\Pages\CreatePage;
use App\Actions\Publishing\IssuePreviewToken;
use App\Actions\Publishing\SchedulePublication;
use App\Actions\Publishing\UpsertSeoMeta;
use App\Models\PreviewToken;
use App\Models\ScheduledPublication;
use App\Models\SeoMeta;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

// ─── Preview tokens ──────────────────────────────────────────────────────────

it('issues a preview token with an expiry', function () {
    $page = app(CreatePage::class)->handle('Preview Test', 'preview-test');
    $token = app(IssuePreviewToken::class)->handle($page, ttlHours: 24);

    expect($token->token)->toHaveLength(48)
        ->and($token->expires_at->isAfter(now()->addHours(23)))->toBeTrue()
        ->and($token->isExpired())->toBeFalse();
});

it('revoking old token on re-issue for the same resource and actor', function () {
    $page = app(CreatePage::class)->handle('Re-issue', 're-issue');

    app(IssuePreviewToken::class)->handle($page);
    app(IssuePreviewToken::class)->handle($page);

    $count = PreviewToken::query()
        ->where('previewable_type', $page->getMorphClass())
        ->where('previewable_id', $page->id)
        ->count();

    expect($count)->toBe(1);
});

it('reports token as expired after ttl', function () {
    $page = app(CreatePage::class)->handle('Stale', 'stale-preview');
    $token = PreviewToken::query()->create([
        'token' => Str::random(48),
        'previewable_type' => $page->getMorphClass(),
        'previewable_id' => $page->id,
        'expires_at' => now()->subMinute(),
    ]);

    expect($token->isExpired())->toBeTrue();
});

// ─── Scheduled publications ──────────────────────────────────────────────────

it('schedules a future publish', function () {
    $page = app(CreatePage::class)->handle('Future', 'future-pub');
    $when = CarbonImmutable::now()->addDay();

    $sched = app(SchedulePublication::class)->handle(
        $page, SchedulePublication::ACTION_PUBLISH, $when
    );

    expect($sched->action)->toBe('publish')
        ->and($sched->scheduled_for->diffInMinutes($when))->toBeLessThan(2)
        ->and($sched->isPending())->toBeTrue();
});

it('replacing a pending schedule of the same type', function () {
    $page = app(CreatePage::class)->handle('Replace', 'replace-sched');
    $when1 = CarbonImmutable::now()->addHours(2);
    $when2 = CarbonImmutable::now()->addHours(4);

    app(SchedulePublication::class)->handle($page, SchedulePublication::ACTION_PUBLISH, $when1);
    app(SchedulePublication::class)->handle($page, SchedulePublication::ACTION_PUBLISH, $when2);

    $count = ScheduledPublication::query()
        ->where('schedulable_type', $page->getMorphClass())
        ->where('schedulable_id', $page->id)
        ->where('action', 'publish')
        ->whereNull('processed_at')
        ->count();

    expect($count)->toBe(1);
});

it('publish and unpublish schedules coexist independently', function () {
    $page = app(CreatePage::class)->handle('Dual', 'dual-sched');

    app(SchedulePublication::class)->handle($page, SchedulePublication::ACTION_PUBLISH, CarbonImmutable::now()->addHours(1));
    app(SchedulePublication::class)->handle($page, SchedulePublication::ACTION_UNPUBLISH, CarbonImmutable::now()->addHours(2));

    $count = ScheduledPublication::query()
        ->where('schedulable_type', $page->getMorphClass())
        ->where('schedulable_id', $page->id)
        ->whereNull('processed_at')
        ->count();

    expect($count)->toBe(2);
});

it('rejects scheduling in the past', function () {
    $page = app(CreatePage::class)->handle('Past', 'past-sched');

    expect(fn () => app(SchedulePublication::class)->handle(
        $page, SchedulePublication::ACTION_PUBLISH, CarbonImmutable::now()->subMinute()
    ))->toThrow(InvalidArgumentException::class, 'future');
});

it('rejects an unknown action', function () {
    $page = app(CreatePage::class)->handle('BadAct', 'bad-act');

    expect(fn () => app(SchedulePublication::class)->handle(
        $page, 'delete', CarbonImmutable::now()->addHour()
    ))->toThrow(InvalidArgumentException::class, 'Unknown');
});

// ─── SEO metadata ────────────────────────────────────────────────────────────

it('creates seo meta for a page', function () {
    $page = app(CreatePage::class)->handle('SEO', 'seo-page');

    $meta = app(UpsertSeoMeta::class)->handle($page, [
        'meta_title' => 'За нас | Платформа',
        'meta_description' => 'Описание на страницата.',
        'robots' => 'index,follow',
        'canonical_path' => '/about',
    ]);

    expect($meta->meta_title)->toBe('За нас | Платформа')
        ->and($meta->robots)->toBe('index,follow')
        ->and($meta->canonical_path)->toBe('/about');
});

it('upserts seo meta — second call updates the same row', function () {
    $page = app(CreatePage::class)->handle('SEO2', 'seo-page-2');

    app(UpsertSeoMeta::class)->handle($page, ['meta_title' => 'First']);
    app(UpsertSeoMeta::class)->handle($page, ['meta_title' => 'Updated']);

    $count = SeoMeta::query()
        ->where('seoable_type', $page->getMorphClass())
        ->where('seoable_id', $page->id)
        ->count();

    expect($count)->toBe(1);

    $meta = SeoMeta::query()
        ->where('seoable_type', $page->getMorphClass())
        ->where('seoable_id', $page->id)
        ->first();

    expect($meta->meta_title)->toBe('Updated');
});

it('rejects an invalid robots value', function () {
    $page = app(CreatePage::class)->handle('Bad Rob', 'bad-robots');

    expect(fn () => app(UpsertSeoMeta::class)->handle($page, ['robots' => 'all']))
        ->toThrow(InvalidArgumentException::class, 'robots');
});

it('rejects an absolute canonical path', function () {
    $page = app(CreatePage::class)->handle('Abs Canon', 'abs-canon');

    expect(fn () => app(UpsertSeoMeta::class)->handle($page, ['canonical_path' => 'https://example.com/page']))
        ->toThrow(InvalidArgumentException::class, 'relative path');
});

it('rejects a base64 og image', function () {
    $page = app(CreatePage::class)->handle('OG Img', 'og-img');

    expect(fn () => app(UpsertSeoMeta::class)->handle($page, [
        'og' => ['image_path' => 'data:image/png;base64,iVBORw0KGgo='],
    ]))->toThrow(InvalidArgumentException::class, 'base64');
});

it('truncates meta_title to 120 characters', function () {
    $page = app(CreatePage::class)->handle('Long', 'long-title');
    $title = str_repeat('а', 200);

    $meta = app(UpsertSeoMeta::class)->handle($page, ['meta_title' => $title]);
    expect(mb_strlen($meta->meta_title))->toBeLessThanOrEqual(120);
});
