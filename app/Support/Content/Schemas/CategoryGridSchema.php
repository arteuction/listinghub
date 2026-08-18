<?php

declare(strict_types=1);

namespace App\Support\Content\Schemas;

use App\Support\Content\BlockSchemaContract;

final class CategoryGridSchema implements BlockSchemaContract
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.title' => ['nullable', 'string', 'max:255'],
            'content.columns' => ['nullable', 'integer', 'min:2', 'max:6'],
            'content.limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function allowedKeys(): array
    {
        return ['title', 'columns', 'limit'];
    }

    public function maxContentSize(): int
    {
        return 2_000;
    }
}
