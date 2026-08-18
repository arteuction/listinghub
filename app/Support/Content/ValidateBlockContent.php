<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Enums\ContentBlockType;
use App\Support\Tiptap\TiptapValidator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ValidateBlockContent
{
    /**
     * Validate content against the block type schema.
     *
     * @param array<string, mixed> $content
     * @throws ValidationException
     */
    public static function validate(ContentBlockType $type, array $content): void
    {
        $schema = BlockSchema::for($type);

        $unknown = BlockSchema::unknownKeys($type, $content);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'content' => 'Unknown content keys: '.implode(', ', $unknown),
            ]);
        }

        $encoded = json_encode($content, JSON_UNESCAPED_UNICODE);
        if ($encoded !== false && strlen($encoded) > $schema->maxContentSize()) {
            throw ValidationException::withMessages([
                'content' => 'Content exceeds maximum size of '.number_format($schema->maxContentSize()).' bytes.',
            ]);
        }

        $validator = Validator::make(
            ['content' => $content],
            $schema->rules(),
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        if (isset($content['tiptap']) && is_array($content['tiptap'])) {
            (new TiptapValidator)->validate($content['tiptap']);
        }
    }
}
