<?php

declare(strict_types=1);

use App\Exceptions\InvalidTiptapContent;
use App\Support\Tiptap\TiptapValidator;

function makeDoc(array ...$children): array
{
    return ['type' => 'doc', 'content' => $children];
}

function para(string $text, array $marks = []): array
{
    $node = ['type' => 'text', 'text' => $text];
    if ($marks !== []) {
        $node['marks'] = $marks;
    }

    return ['type' => 'paragraph', 'content' => [$node]];
}

function heading(int $level, string $text): array
{
    return ['type' => 'heading', 'attrs' => ['level' => $level], 'content' => [['type' => 'text', 'text' => $text]]];
}

beforeEach(function () {
    $this->validator = new TiptapValidator;
});

it('accepts a minimal valid document', function () {
    $doc = makeDoc(para('Hello world'));
    expect(fn () => $this->validator->validate($doc))->not->toThrow(InvalidTiptapContent::class);
});

it('accepts all allowed node types', function () {
    $doc = makeDoc(
        heading(2, 'Section'),
        para('Body text'),
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Item']]]]],
        ]],
        ['type' => 'orderedList', 'content' => [
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Item']]]]],
        ]],
        ['type' => 'blockquote', 'content' => [para('Quote')]],
    );
    expect(fn () => $this->validator->validate($doc))->not->toThrow(InvalidTiptapContent::class);
});

it('accepts all allowed marks', function () {
    $doc = makeDoc(para('x', [['type' => 'bold']]), para('x', [['type' => 'italic']]), para('x', [['type' => 'strike']]));
    expect(fn () => $this->validator->validate($doc))->not->toThrow(InvalidTiptapContent::class);
});

it('accepts a valid https link mark', function () {
    $doc = makeDoc(para('click', [['type' => 'link', 'attrs' => ['href' => 'https://example.com']]]));
    expect(fn () => $this->validator->validate($doc))->not->toThrow(InvalidTiptapContent::class);
});

it('accepts a relative link mark', function () {
    $doc = makeDoc(para('more', [['type' => 'link', 'attrs' => ['href' => '/about']]]));
    expect(fn () => $this->validator->validate($doc))->not->toThrow(InvalidTiptapContent::class);
});

it('rejects a javascript: link', function () {
    $doc = makeDoc(para('evil', [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]));
    expect(fn () => $this->validator->validate($doc))->toThrow(InvalidTiptapContent::class, 'href');
});

it('rejects a data: link', function () {
    $doc = makeDoc(para('evil', [['type' => 'link', 'attrs' => ['href' => 'data:text/html,<script>']]]));
    expect(fn () => $this->validator->validate($doc))->toThrow(InvalidTiptapContent::class, 'href');
});

it('rejects a disallowed node type', function () {
    $doc = ['type' => 'doc', 'content' => [['type' => 'codeBlock', 'content' => []]]];
    expect(fn () => $this->validator->validate($doc))->toThrow(InvalidTiptapContent::class, 'codeBlock');
});

it('rejects a disallowed mark type', function () {
    $doc = makeDoc(para('x', [['type' => 'code']]));
    expect(fn () => $this->validator->validate($doc))->toThrow(InvalidTiptapContent::class, 'code');
});

it('rejects heading level 1', function () {
    $doc = makeDoc(heading(1, 'Page title'));
    expect(fn () => $this->validator->validate($doc))->toThrow(InvalidTiptapContent::class, 'level 1');
});

it('rejects heading level 4', function () {
    $doc = makeDoc(heading(4, 'Sub-sub'));
    expect(fn () => $this->validator->validate($doc))->toThrow(InvalidTiptapContent::class, 'level 4');
});

it('accepts heading levels 2 and 3', function () {
    $doc = makeDoc(heading(2, 'H2'), heading(3, 'H3'));
    expect(fn () => $this->validator->validate($doc))->not->toThrow(InvalidTiptapContent::class);
});

it('rejects a disallowed node attribute', function () {
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'attrs' => ['class' => 'evil'], 'content' => []],
    ]];
    expect(fn () => $this->validator->validate($doc))->toThrow(InvalidTiptapContent::class, 'class');
});

it('rejects a root type that is not doc', function () {
    $doc = ['type' => 'paragraph', 'content' => []];
    expect(fn () => $this->validator->validate($doc))->toThrow(InvalidTiptapContent::class, 'doc');
});

it('rejects a document that exceeds max depth', function () {
    $node = ['type' => 'blockquote', 'content' => []];
    // Build a chain 12 levels deep (max is 10)
    for ($i = 0; $i < 12; $i++) {
        $node = ['type' => 'blockquote', 'content' => [$node]];
    }
    $doc = ['type' => 'doc', 'content' => [$node]];
    expect(fn () => $this->validator->validate($doc))->toThrow(InvalidTiptapContent::class, 'depth');
});

it('rejects an empty link href', function () {
    $doc = makeDoc(para('x', [['type' => 'link', 'attrs' => ['href' => '']]]));
    expect(fn () => $this->validator->validate($doc))->toThrow(InvalidTiptapContent::class, 'href');
});
