<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

final class InvalidTiptapContent extends InvalidArgumentException
{
    public static function disallowedNodeType(string $type, string $path): self
    {
        return new self("Disallowed Tiptap node type \"{$type}\" at {$path}.");
    }

    public static function disallowedMarkType(string $type, string $path): self
    {
        return new self("Disallowed Tiptap mark type \"{$type}\" at {$path}.");
    }

    public static function disallowedAttribute(string $attr, string $nodeType, string $path): self
    {
        return new self("Disallowed attribute \"{$attr}\" on node \"{$nodeType}\" at {$path}.");
    }

    public static function disallowedHeadingLevel(int $level): self
    {
        return new self("Heading level {$level} is not permitted. Allowed levels: 2, 3.");
    }

    public static function invalidLinkHref(string $href): self
    {
        return new self("Link href \"{$href}\" does not meet the allowed-scheme policy.");
    }

    public static function rootNotDoc(string $actual): self
    {
        return new self("Tiptap document root must be type \"doc\"; got \"{$actual}\".");
    }

    public static function depthExceeded(int $max): self
    {
        return new self("Tiptap document exceeds the maximum nesting depth of {$max}.");
    }

    public static function nodeCountExceeded(int $max): self
    {
        return new self("Tiptap document exceeds the maximum node count of {$max}.");
    }

    public static function malformed(string $reason): self
    {
        return new self("Malformed Tiptap document: {$reason}.");
    }
}
