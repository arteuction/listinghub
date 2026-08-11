<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Raised when a search request references an unknown/non-filterable field, an
 * unsupported operator, an invalid value cast, or otherwise violates the query
 * contract. The compiler REJECTS such requests rather than ignoring them.
 */
class InvalidSearchQuery extends DomainException
{
    public static function make(string $message): self
    {
        return new self($message);
    }
}
