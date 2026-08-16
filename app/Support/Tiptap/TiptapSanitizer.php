<?php

declare(strict_types=1);

namespace App\Support\Tiptap;

use Mews\Purifier\Facades\Purifier;

/**
 * Converts a validated Tiptap JSON document to a sanitized HTML string.
 *
 * Render → HTMLPurifier gives us defence-in-depth: the renderer is the
 * expected path; HTMLPurifier is the safety net for schema drift.
 */
final class TiptapSanitizer
{
    public function __construct(private readonly TiptapHtmlRenderer $renderer) {}

    /** @param array<string, mixed> $doc */
    public function toHtml(array $doc): string
    {
        $raw = $this->renderer->render($doc);

        return Purifier::clean($raw, config('tiptap.purifier', []));
    }
}
