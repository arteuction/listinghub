<?php

declare(strict_types=1);

namespace App\Support\Tiptap;

use App\Exceptions\InvalidTiptapContent;

/**
 * Validates a decoded Tiptap document against the platform allow-list.
 *
 * Call validate() before persisting any rich-text content. The renderer
 * intentionally does NOT call the validator; callers must validate on write
 * and may rely on HTMLPurifier as the defensive read-time layer.
 */
final class TiptapValidator
{
    /** Node types that may carry attrs and which attributes they allow. */
    private const NODE_ATTRS = [
        'doc'         => [],
        'paragraph'   => [],
        'heading'     => ['level'],
        'bulletList'  => [],
        'orderedList' => ['start'],
        'listItem'    => [],
        'blockquote'  => [],
        'hardBreak'   => [],
        'text'        => [],
    ];

    /** Mark types and their allowed attributes. */
    private const MARK_ATTRS = [
        'bold'   => [],
        'italic' => [],
        'strike' => [],
        'link'   => ['href', 'target', 'rel', 'class'],
    ];

    private int $nodeCount = 0;

    /** @param array<string, mixed> $doc Decoded Tiptap JSON */
    public function validate(array $doc): void
    {
        $this->nodeCount = 0;
        $this->validateNode($doc, 'root', 0);
    }

    /** @param array<string, mixed> $node */
    private function validateNode(array $node, string $path, int $depth): void
    {
        $maxDepth = (int) config('tiptap.max_depth', 10);
        $maxNodes = (int) config('tiptap.max_nodes', 2000);

        if ($depth > $maxDepth) {
            throw InvalidTiptapContent::depthExceeded($maxDepth);
        }

        $this->nodeCount++;

        if ($this->nodeCount > $maxNodes) {
            throw InvalidTiptapContent::nodeCountExceeded($maxNodes);
        }

        if (! isset($node['type']) || ! is_string($node['type'])) {
            throw InvalidTiptapContent::malformed("node at {$path} missing \"type\" string");
        }

        $type = $node['type'];

        if ($path === 'root' && $type !== 'doc') {
            throw InvalidTiptapContent::rootNotDoc($type);
        }

        $allowedNodes = config('tiptap.allowed_nodes', []);
        if (! in_array($type, $allowedNodes, true)) {
            throw InvalidTiptapContent::disallowedNodeType($type, $path);
        }

        // Validate attrs
        if (isset($node['attrs'])) {
            if (! is_array($node['attrs'])) {
                throw InvalidTiptapContent::malformed("attrs at {$path} must be an object");
            }

            $allowedAttrs = self::NODE_ATTRS[$type] ?? [];
            foreach (array_keys($node['attrs']) as $attr) {
                if (! in_array($attr, $allowedAttrs, true)) {
                    throw InvalidTiptapContent::disallowedAttribute($attr, $type, $path);
                }
            }

            if ($type === 'heading') {
                $this->validateHeadingLevel($node['attrs']);
            }
        }

        // Validate marks on text nodes
        if (isset($node['marks'])) {
            if (! is_array($node['marks'])) {
                throw InvalidTiptapContent::malformed("marks at {$path} must be an array");
            }

            foreach ($node['marks'] as $i => $mark) {
                $this->validateMark($mark, "{$path}.marks[{$i}]");
            }
        }

        // Recurse into children
        if (isset($node['content'])) {
            if (! is_array($node['content'])) {
                throw InvalidTiptapContent::malformed("content at {$path} must be an array");
            }

            foreach ($node['content'] as $i => $child) {
                if (! is_array($child)) {
                    throw InvalidTiptapContent::malformed("child at {$path}.content[{$i}] must be an object");
                }
                $this->validateNode($child, "{$path}.content[{$i}]", $depth + 1);
            }
        }
    }

    /** @param array<string, mixed> $attrs */
    private function validateHeadingLevel(array $attrs): void
    {
        if (! isset($attrs['level'])) {
            return;
        }

        $level = $attrs['level'];
        $allowed = config('tiptap.heading_levels', [2, 3]);

        if (! in_array($level, $allowed, true)) {
            throw InvalidTiptapContent::disallowedHeadingLevel((int) $level);
        }
    }

    /** @param mixed $mark */
    private function validateMark(mixed $mark, string $path): void
    {
        if (! is_array($mark)) {
            throw InvalidTiptapContent::malformed("mark at {$path} must be an object");
        }

        if (! isset($mark['type']) || ! is_string($mark['type'])) {
            throw InvalidTiptapContent::malformed("mark at {$path} missing \"type\" string");
        }

        $type = $mark['type'];
        $allowedMarks = config('tiptap.allowed_marks', []);

        if (! in_array($type, $allowedMarks, true)) {
            throw InvalidTiptapContent::disallowedMarkType($type, $path);
        }

        if (isset($mark['attrs'])) {
            if (! is_array($mark['attrs'])) {
                throw InvalidTiptapContent::malformed("mark attrs at {$path} must be an object");
            }

            $allowedAttrs = self::MARK_ATTRS[$type] ?? [];
            foreach (array_keys($mark['attrs']) as $attr) {
                if (! in_array($attr, $allowedAttrs, true)) {
                    throw InvalidTiptapContent::disallowedAttribute($attr, $type, $path);
                }
            }

            if ($type === 'link') {
                $this->validateLinkHref($mark['attrs']);
            }
        }
    }

    /** @param array<string, mixed> $attrs */
    private function validateLinkHref(array $attrs): void
    {
        $href = $attrs['href'] ?? '';

        if (! is_string($href) || $href === '') {
            throw InvalidTiptapContent::invalidLinkHref($href);
        }

        // Relative internal links are always fine
        if (str_starts_with($href, '/')) {
            return;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);
        $allowed = config('tiptap.link.allowed_schemes', ['https', 'http']);

        if ($scheme === null || ! in_array(strtolower($scheme), $allowed, true)) {
            throw InvalidTiptapContent::invalidLinkHref($href);
        }
    }
}
