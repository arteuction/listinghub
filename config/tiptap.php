<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed Tiptap node and mark types
    |--------------------------------------------------------------------------
    |
    | Only these types may appear in a document submitted to the platform.
    | Any node or mark type not listed here causes the validator to reject
    | the document. Extending the allow-list requires updating this file,
    | TiptapSchema, and adding a render case in TiptapHtmlRenderer.
    |
    */

    'allowed_nodes' => [
        'doc',
        'paragraph',
        'heading',
        'bulletList',
        'orderedList',
        'listItem',
        'blockquote',
        'hardBreak',
        'text',
    ],

    'allowed_marks' => [
        'bold',
        'italic',
        'strike',
        'link',
    ],

    /*
    |--------------------------------------------------------------------------
    | Heading level allowlist
    |--------------------------------------------------------------------------
    |
    | H1 is reserved for the page title. Only H2 and H3 may appear inside
    | rich-text blocks.
    |
    */

    'heading_levels' => [2, 3],

    /*
    |--------------------------------------------------------------------------
    | Link constraints
    |--------------------------------------------------------------------------
    |
    | Relative paths starting with "/" are always permitted (internal links).
    | Absolute URLs are restricted to the listed schemes. No javascript:,
    | data:, vbscript: or blob: URIs are accepted.
    |
    */

    'link' => [
        'allowed_schemes' => ['https', 'http'],
        'allow_relative'  => true,
        'force_rel'       => 'noopener noreferrer nofollow',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum document depth
    |--------------------------------------------------------------------------
    |
    | Prevents maliciously nested documents from causing stack overflows.
    |
    */

    'max_depth' => 10,

    /*
    |--------------------------------------------------------------------------
    | Maximum node count
    |--------------------------------------------------------------------------
    */

    'max_nodes' => 2000,

    /*
    |--------------------------------------------------------------------------
    | HTMLPurifier profile used when rendering Tiptap JSON to HTML
    |--------------------------------------------------------------------------
    */

    'purifier' => [
        'Core.Encoding'              => 'UTF-8',
        'HTML.Doctype'               => 'HTML 4.01 Transitional',
        'HTML.Allowed'               => 'h2,h3,p,br,strong,em,s,a[href|target|rel],ul,ol,li,blockquote',
        'HTML.AllowedAttributes'     => 'a.href,a.target,a.rel',
        'Attr.AllowedFrameTargets'   => ['_blank'],
        'Attr.AllowedRel'            => 'noopener,noreferrer,nofollow',
        'AutoFormat.AutoParagraph'   => false,
        'AutoFormat.RemoveEmpty'     => true,
        'URI.AllowedSchemes'         => ['http' => true, 'https' => true],
        'URI.DisableExternalResources' => false,
        'Output.Newline'             => "\n",
    ],

];
