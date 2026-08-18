<?php

declare(strict_types=1);

namespace App\Support\Content\Schemas;

use App\Support\Content\BlockSchemaContract;

final class AnnouncementSchema implements BlockSchemaContract
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.text' => ['required', 'string', 'max:2000'],
            'content.style' => ['nullable', 'string', 'in:info,warning,success'],
        ];
    }

    public function allowedKeys(): array
    {
        return ['text', 'style'];
    }

    public function maxContentSize(): int
    {
        return 4_000;
    }
}
