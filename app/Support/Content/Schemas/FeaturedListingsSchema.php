<?php

declare(strict_types=1);

namespace App\Support\Content\Schemas;

use App\Support\Content\BlockSchemaContract;

final class FeaturedListingsSchema implements BlockSchemaContract
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.title' => ['nullable', 'string', 'max:255'],
            'content.limit' => ['nullable', 'integer', 'min:1', 'max:24'],
            'content.category_id' => ['nullable', 'integer'],
        ];
    }

    public function allowedKeys(): array
    {
        return ['title', 'limit', 'category_id'];
    }

    public function maxContentSize(): int
    {
        return 2_000;
    }
}
