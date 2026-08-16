<?php

declare(strict_types=1);

namespace App\Rules;

use App\Exceptions\InvalidTiptapContent;
use App\Support\Tiptap\TiptapValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value is a well-formed, allow-listed Tiptap document.
 * The value may arrive as a JSON string or a decoded array.
 */
final class TiptapDocument implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (! is_array($decoded)) {
                $fail('The :attribute must be a valid Tiptap JSON document.');

                return;
            }
            $value = $decoded;
        }

        if (! is_array($value)) {
            $fail('The :attribute must be a Tiptap document object.');

            return;
        }

        try {
            (new TiptapValidator)->validate($value);
        } catch (InvalidTiptapContent $e) {
            $fail($e->getMessage());
        }
    }
}
