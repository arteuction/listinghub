<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidClaimDocument extends RuntimeException
{
    public static function tooLarge(): self
    {
        return new self('Документът надвишава максималния позволен размер.');
    }

    public static function unsupportedType(): self
    {
        return new self('Позволени са само PDF, JPEG и PNG документи.');
    }

    public static function notReadable(): self
    {
        return new self('Файлът не може да бъде прочетен.');
    }
}
