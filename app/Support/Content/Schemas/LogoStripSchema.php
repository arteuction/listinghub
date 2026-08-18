<?php

declare(strict_types=1);

namespace App\Support\Content\Schemas;

use App\Support\Content\BlockSchemaContract;

final class LogoStripSchema implements BlockSchemaContract
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.title' => ['nullable', 'string', 'max:255'],
            'content.logos' => ['required', 'array', 'min:1', 'max:30'],
            'content.logos.*.image_url' => ['required', 'string', 'max:2048'],
            'content.logos.*.alt' => ['required', 'string', 'max:255'],
            'content.logos.*.link_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function allowedKeys(): array
    {
        return ['title', 'logos'];
    }

    public function maxContentSize(): int
    {
        return 64_000;
    }
}
