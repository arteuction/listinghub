<?php

declare(strict_types=1);

use App\Exceptions\InvalidTiptapContent;
use App\Support\Tiptap\TiptapHtmlRenderer;

beforeEach(function () {
    $this->renderer = new TiptapHtmlRenderer;
});

it('renders a paragraph', function () {
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]],
    ]];
    expect($this->renderer->render($doc))->toBe('<p>Hello</p>');
});

it('renders heading levels 2 and 3', function () {
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'A']]],
        ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'B']]],
    ]];
    expect($this->renderer->render($doc))->toBe('<h2>A</h2><h3>B</h3>');
});

it('renders bold, italic and strike marks', function () {
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => 'y', 'marks' => [['type' => 'italic']]],
            ['type' => 'text', 'text' => 'z', 'marks' => [['type' => 'strike']]],
        ]],
    ]];
    expect($this->renderer->render($doc))->toBe('<p><strong>x</strong><em>y</em><s>z</s></p>');
});

it('renders a link with forced rel attribute', function () {
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'click', 'marks' => [
                ['type' => 'link', 'attrs' => ['href' => 'https://example.com']],
            ]],
        ]],
    ]];
    $html = $this->renderer->render($doc);
    expect($html)->toContain('href="https://example.com"')
        ->and($html)->toContain('rel="noopener noreferrer nofollow"');
});

it('renders a bullet list', function () {
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Item']]],
            ]],
        ]],
    ]];
    expect($this->renderer->render($doc))->toBe('<ul><li><p>Item</p></li></ul>');
});

it('renders an ordered list with start attribute', function () {
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'orderedList', 'attrs' => ['start' => 3], 'content' => [
            ['type' => 'listItem', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Third']]],
            ]],
        ]],
    ]];
    expect($this->renderer->render($doc))->toContain('start="3"');
});

it('escapes HTML special characters in text', function () {
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '<script>alert(1)</script>']]],
    ]];
    $html = $this->renderer->render($doc);
    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('renders a hard break', function () {
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Line 1'],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'Line 2'],
        ]],
    ]];
    expect($this->renderer->render($doc))->toBe('<p>Line 1<br>Line 2</p>');
});

it('throws when root is not doc', function () {
    expect(fn () => $this->renderer->render(['type' => 'paragraph']))
        ->toThrow(InvalidTiptapContent::class);
});

it('renders a blockquote', function () {
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'blockquote', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Quote']]],
        ]],
    ]];
    expect($this->renderer->render($doc))->toBe('<blockquote><p>Quote</p></blockquote>');
});

it('ignores unknown node types silently at render time', function () {
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'unknownFuture', 'content' => []],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Safe']]],
    ]];
    // Renderer falls back to '' for unknown — purifier drops it
    expect($this->renderer->render($doc))->toBe('<p>Safe</p>');
});
