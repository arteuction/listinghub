<?php

declare(strict_types=1);

namespace App\Support\Tiptap;

use App\Exceptions\InvalidTiptapContent;

/**
 * Converts a Tiptap JSON document to an HTML string.
 *
 * The output is intentionally un-sanitized; always pipe through
 * TiptapSanitizer (which runs HTMLPurifier) before sending to a browser.
 * On the write path the document must be validated first via TiptapValidator.
 */
final class TiptapHtmlRenderer
{
    /** @param array<string, mixed> $doc */
    public function render(array $doc): string
    {
        if (($doc['type'] ?? '') !== 'doc') {
            throw InvalidTiptapContent::rootNotDoc($doc['type'] ?? 'missing');
        }

        return $this->renderChildren($doc);
    }

    /** @param array<string, mixed> $node */
    private function renderNode(array $node): string
    {
        $type = $node['type'] ?? '';

        return match ($type) {
            'doc' => $this->renderChildren($node),
            'paragraph' => '<p>'.$this->renderChildren($node).'</p>',
            'heading' => $this->renderHeading($node),
            'bulletList' => '<ul>'.$this->renderChildren($node).'</ul>',
            'orderedList' => $this->renderOrderedList($node),
            'listItem' => '<li>'.$this->renderChildren($node).'</li>',
            'blockquote' => '<blockquote>'.$this->renderChildren($node).'</blockquote>',
            'hardBreak' => '<br>',
            'text' => $this->renderText($node),
            default => '',
        };
    }

    /** @param array<string, mixed> $node */
    private function renderChildren(array $node): string
    {
        $html = '';
        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                $html .= $this->renderNode($child);
            }
        }

        return $html;
    }

    /** @param array<string, mixed> $node */
    private function renderHeading(array $node): string
    {
        $level = (int) ($node['attrs']['level'] ?? 2);
        $level = max(2, min(3, $level));

        return "<h{$level}>".$this->renderChildren($node)."</h{$level}>";
    }

    /** @param array<string, mixed> $node */
    private function renderOrderedList(array $node): string
    {
        $start = (int) ($node['attrs']['start'] ?? 1);
        $attr = $start !== 1 ? " start=\"{$start}\"" : '';

        return "<ol{$attr}>".$this->renderChildren($node).'</ol>';
    }

    /** @param array<string, mixed> $node */
    private function renderText(array $node): string
    {
        $text = htmlspecialchars((string) ($node['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        foreach (array_reverse($node['marks'] ?? []) as $mark) {
            if (! is_array($mark)) {
                continue;
            }
            $text = $this->applyMark($text, $mark);
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $mark
     */
    private function applyMark(string $text, array $mark): string
    {
        $type = $mark['type'] ?? '';

        return match ($type) {
            'bold' => "<strong>{$text}</strong>",
            'italic' => "<em>{$text}</em>",
            'strike' => "<s>{$text}</s>",
            'link' => $this->renderLink($text, $mark['attrs'] ?? []),
            default => $text,
        };
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function renderLink(string $text, array $attrs): string
    {
        $href = htmlspecialchars((string) ($attrs['href'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $target = isset($attrs['target']) ? ' target="_blank"' : '';
        $rel = ' rel="'.htmlspecialchars(config('tiptap.link.force_rel', 'noopener noreferrer nofollow'), ENT_QUOTES, 'UTF-8').'"';

        return "<a href=\"{$href}\"{$target}{$rel}>{$text}</a>";
    }
}
