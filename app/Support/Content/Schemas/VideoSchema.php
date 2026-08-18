<?php

declare(strict_types=1);

namespace App\Support\Content\Schemas;

use App\Support\Content\BlockSchemaContract;

final class VideoSchema implements BlockSchemaContract
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.title' => ['nullable', 'string', 'max:255'],
            'content.url' => ['required', 'string', 'max:2048', 'regex:/^https:\/\/(www\.)?(youtube\.com|youtu\.be|vimeo\.com)\//'],
            'content.aspect_ratio' => ['nullable', 'string', 'in:16:9,4:3,1:1'],
        ];
    }

    public function allowedKeys(): array
    {
        return ['title', 'url', 'aspect_ratio'];
    }

    public function maxContentSize(): int
    {
        return 4_000;
    }
}
