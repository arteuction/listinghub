<?php

declare(strict_types=1);

namespace App\Support\Content\Schemas;

use App\Support\Content\BlockSchemaContract;

final class RichTextSchema implements BlockSchemaContract
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.tiptap' => ['required', 'array'],
        ];
    }

    public function allowedKeys(): array
    {
        return ['tiptap'];
    }

    public function maxContentSize(): int
    {
        return 512_000; // 500 KB
    }
}
