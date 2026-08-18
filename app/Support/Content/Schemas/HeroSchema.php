<?php

declare(strict_types=1);

namespace App\Support\Content\Schemas;

use App\Support\Content\BlockSchemaContract;

final class HeroSchema implements BlockSchemaContract
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.title' => ['required', 'string', 'max:255'],
            'content.subtitle' => ['nullable', 'string', 'max:500'],
            'content.cta_text' => ['nullable', 'string', 'max:100'],
            'content.cta_url' => ['nullable', 'string', 'max:2048', 'regex:/^(\/[^\s]*|https:\/\/[^\s]+)$/'],
            'content.image_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function allowedKeys(): array
    {
        return ['title', 'subtitle', 'cta_text', 'cta_url', 'image_url'];
    }

    public function maxContentSize(): int
    {
        return 8_000;
    }
}
