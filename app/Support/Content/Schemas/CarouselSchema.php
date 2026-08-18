<?php

declare(strict_types=1);

namespace App\Support\Content\Schemas;

use App\Support\Content\BlockSchemaContract;

final class CarouselSchema implements BlockSchemaContract
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.title' => ['nullable', 'string', 'max:255'],
            'content.slides' => ['required', 'array', 'min:1', 'max:20'],
            'content.slides.*.image_url' => ['required', 'string', 'max:2048'],
            'content.slides.*.alt' => ['nullable', 'string', 'max:255'],
            'content.slides.*.caption' => ['nullable', 'string', 'max:500'],
            'content.slides.*.link_url' => ['nullable', 'string', 'max:2048'],
            'content.autoplay' => ['nullable', 'boolean'],
        ];
    }

    public function allowedKeys(): array
    {
        return ['title', 'slides', 'autoplay'];
    }

    public function maxContentSize(): int
    {
        return 64_000;
    }
}
