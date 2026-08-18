<?php

declare(strict_types=1);

namespace App\Support\Content\Schemas;

use App\Support\Content\BlockSchemaContract;

final class ImageTextSchema implements BlockSchemaContract
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.title' => ['nullable', 'string', 'max:255'],
            'content.tiptap' => ['nullable', 'array'],
            'content.image_url' => ['nullable', 'string', 'max:2048'],
            'content.image_alt' => ['nullable', 'string', 'max:255'],
            'content.layout' => ['nullable', 'string', 'in:left,right'],
        ];
    }

    public function allowedKeys(): array
    {
        return ['title', 'tiptap', 'image_url', 'image_alt', 'layout'];
    }

    public function maxContentSize(): int
    {
        return 256_000;
    }
}
