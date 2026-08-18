<?php

declare(strict_types=1);

namespace App\Support\Content\Schemas;

use App\Support\Content\BlockSchemaContract;

final class FaqSchema implements BlockSchemaContract
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.title' => ['nullable', 'string', 'max:255'],
            'content.items' => ['required', 'array', 'min:1', 'max:50'],
            'content.items.*.question' => ['required', 'string', 'max:500'],
            'content.items.*.answer' => ['required', 'string', 'max:5000'],
        ];
    }

    public function allowedKeys(): array
    {
        return ['title', 'items'];
    }

    public function maxContentSize(): int
    {
        return 256_000;
    }
}
