<?php

declare(strict_types=1);

namespace App\Support\Content\Schemas;

use App\Support\Content\BlockSchemaContract;

final class GallerySchema implements BlockSchemaContract
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.title' => ['nullable', 'string', 'max:255'],
            'content.images' => ['required', 'array', 'min:1', 'max:50'],
            'content.images.*.url' => ['required', 'string', 'max:2048'],
            'content.images.*.alt' => ['nullable', 'string', 'max:255'],
            'content.images.*.caption' => ['nullable', 'string', 'max:500'],
            'content.columns' => ['nullable', 'integer', 'min:2', 'max:6'],
        ];
    }

    public function allowedKeys(): array
    {
        return ['title', 'images', 'columns'];
    }

    public function maxContentSize(): int
    {
        return 128_000;
    }
}
