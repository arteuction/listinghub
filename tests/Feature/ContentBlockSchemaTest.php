<?php

declare(strict_types=1);

use App\Actions\Content\CreateContentBlock;
use App\Enums\ContentBlockType;
use App\Support\Content\BlockSchema;
use Illuminate\Validation\ValidationException;

it('accepts valid hero content', function () {
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::Hero,
        ['title' => 'Добре дошли', 'subtitle' => 'Намерете бизнес', 'cta_text' => 'Търси', 'cta_url' => '/search'],
    );

    expect($block->content['title'])->toBe('Добре дошли');
});

it('rejects hero without title', function () {
    app(CreateContentBlock::class)->handle(
        ContentBlockType::Hero,
        ['subtitle' => 'Без заглавие'],
    );
})->throws(ValidationException::class);

it('rejects hero with unknown keys', function () {
    app(CreateContentBlock::class)->handle(
        ContentBlockType::Hero,
        ['title' => 'Тест', 'malicious_script' => '<script>alert(1)</script>'],
    );
})->throws(ValidationException::class);

it('rejects hero with javascript: URL scheme', function () {
    app(CreateContentBlock::class)->handle(
        ContentBlockType::Hero,
        ['title' => 'XSS', 'cta_text' => 'Click', 'cta_url' => 'javascript:alert(1)'],
    );
})->throws(ValidationException::class);

it('accepts valid faq content', function () {
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::Faq,
        [
            'title' => 'ЧЗВ',
            'items' => [
                ['question' => 'Какво е ListingHub?', 'answer' => 'Платформа за бизнес директории.'],
            ],
        ],
    );

    expect($block->content['items'])->toHaveCount(1);
});

it('rejects faq with empty items', function () {
    app(CreateContentBlock::class)->handle(
        ContentBlockType::Faq,
        ['title' => 'ЧЗВ', 'items' => []],
    );
})->throws(ValidationException::class);

it('rejects faq items without answer', function () {
    app(CreateContentBlock::class)->handle(
        ContentBlockType::Faq,
        ['items' => [['question' => 'Въпрос?']]],
    );
})->throws(ValidationException::class);

it('accepts valid announcement content', function () {
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::Announcement,
        ['text' => 'Ново: платформата е обновена!'],
    );

    expect($block->content['text'])->toContain('обновена');
});

it('rejects announcement without text', function () {
    app(CreateContentBlock::class)->handle(
        ContentBlockType::Announcement,
        ['style' => 'info'],
    );
})->throws(ValidationException::class);

it('accepts valid rich_text content', function () {
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText,
        ['tiptap' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Текст']]]]]],
    );

    expect($block->content)->toHaveKey('tiptap');
});

it('rejects rich_text without tiptap key', function () {
    app(CreateContentBlock::class)->handle(
        ContentBlockType::RichText,
        ['html' => '<p>Bad</p>'],
    );
})->throws(ValidationException::class);

it('returns correct allowed keys per type', function () {
    expect(BlockSchema::for(ContentBlockType::Hero)->allowedKeys())
        ->toContain('title', 'subtitle', 'cta_text', 'cta_url')
        ->and(BlockSchema::for(ContentBlockType::Faq)->allowedKeys())
        ->toContain('title', 'items')
        ->and(BlockSchema::for(ContentBlockType::RichText)->allowedKeys())
        ->toBe(['tiptap']);
});

it('detects unknown keys for each type', function () {
    $unknown = BlockSchema::unknownKeys(ContentBlockType::Hero, [
        'title' => 'OK',
        'evil' => 'payload',
        'also_bad' => true,
    ]);

    expect($unknown)->toBe(['evil', 'also_bad']);
});

it('rejects video with non-allowed domain', function () {
    app(CreateContentBlock::class)->handle(
        ContentBlockType::Video,
        ['url' => 'https://evil.com/exploit.mp4'],
    );
})->throws(ValidationException::class);

it('accepts video with youtube URL', function () {
    $block = app(CreateContentBlock::class)->handle(
        ContentBlockType::Video,
        ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    );

    expect($block->content['url'])->toContain('youtube.com');
});
