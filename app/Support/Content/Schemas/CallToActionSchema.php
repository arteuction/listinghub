<?php

declare(strict_types=1);

namespace App\Support\Content\Schemas;

use App\Support\Content\BlockSchemaContract;

final class CallToActionSchema implements BlockSchemaContract
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.title' => ['required', 'string', 'max:255'],
            'content.description' => ['nullable', 'string', 'max:1000'],
            'content.cta_text' => ['required', 'string', 'max:100'],
            'content.cta_url' => ['required', 'string', 'max:2048', 'regex:/^(\/[^\s]*|https:\/\/[^\s]+)$/'],
            'content.style' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function allowedKeys(): array
    {
        return ['title', 'description', 'cta_text', 'cta_url', 'style'];
    }

    public function maxContentSize(): int
    {
        return 8_000;
    }
}
