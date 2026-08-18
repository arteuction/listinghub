<?php

declare(strict_types=1);

namespace App\Support\Content\Schemas;

use App\Support\Content\BlockSchemaContract;

final class TestimonialsSchema implements BlockSchemaContract
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.title' => ['nullable', 'string', 'max:255'],
            'content.items' => ['required', 'array', 'min:1', 'max:20'],
            'content.items.*.name' => ['required', 'string', 'max:255'],
            'content.items.*.text' => ['required', 'string', 'max:2000'],
            'content.items.*.company' => ['nullable', 'string', 'max:255'],
            'content.items.*.avatar_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function allowedKeys(): array
    {
        return ['title', 'items'];
    }

    public function maxContentSize(): int
    {
        return 128_000;
    }
}
