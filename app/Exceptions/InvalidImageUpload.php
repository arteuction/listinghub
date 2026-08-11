<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * A file that survived request validation but is not a usable, safe image.
 *
 * Messages are shown to members, so they stay generic: the exact reason a file
 * was rejected is useful to an attacker probing what the filter accepts.
 */
class InvalidImageUpload extends DomainException
{
    public static function notAnImage(): self
    {
        return new self('Файлът не е валидно изображение.');
    }

    public static function unsupportedType(): self
    {
        return new self('Поддържат се само JPEG, PNG и WebP.');
    }

    public static function tooLarge(): self
    {
        return new self('Изображението е твърде голямо.');
    }
}
