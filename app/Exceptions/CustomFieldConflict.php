<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Raised when a custom-field operation violates an invariant
 * (docs/DECLARATIVE_FIELDS.md §2A I1–I8 / §5). Always thrown before any write,
 * so the caller's transaction leaves the database unchanged.
 *
 * When the conflict concerns a specific field, `fieldKey` carries its key so a
 * controller can surface a per-field validation error.
 */
class CustomFieldConflict extends DomainException
{
    public ?string $fieldKey = null;

    public static function make(string $message): self
    {
        return new self($message);
    }

    public static function forField(string $key, string $message): self
    {
        $e = new self($message);
        $e->fieldKey = $key;

        return $e;
    }
}
