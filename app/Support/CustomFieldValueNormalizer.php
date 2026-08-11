<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CustomFieldType;
use App\Exceptions\CustomFieldConflict;

/**
 * Turns a raw input into the correct typed column + value, or null when empty.
 * The single source of truth for value validity — used by both the backfill
 * migration and SyncListingCustomFields, so they can never diverge.
 *
 * All numeric handling is string-based: no float ever touches a value.
 */
final class CustomFieldValueNormalizer
{
    private const STRING_MAX = 255;

    private const DECIMAL_INT_DIGITS = 16;

    private const DECIMAL_FRAC_DIGITS = 4;

    /**
     * @param  array<int, array{value:string,label?:string}>|null  $options  active select options
     * @return array{column:string,value:string|bool}|null  null == empty (row should be deleted)
     */
    public function normalize(CustomFieldType $type, mixed $raw, ?array $options = null): ?array
    {
        if ($this->isEmpty($raw, $type)) {
            return null;
        }

        return match ($type) {
            CustomFieldType::Text => ['column' => 'value_text', 'value' => $this->text($raw)],
            CustomFieldType::Number => ['column' => 'value_decimal', 'value' => $this->decimal($raw)],
            CustomFieldType::Checkbox => ['column' => 'value_boolean', 'value' => $this->boolean($raw)],
            CustomFieldType::Select => ['column' => 'value_string', 'value' => $this->select($raw, $options)],
            CustomFieldType::Url => ['column' => 'value_string', 'value' => $this->url($raw)],
            CustomFieldType::Email => ['column' => 'value_string', 'value' => $this->email($raw)],
        };
    }

    /**
     * Typed cast of a filter/sort input for comparison — same rules as storage
     * but WITHOUT select-option membership (a filter may reference any value; a
     * non-matching one simply returns no rows). Throws on an invalid cast.
     */
    public function castForFilter(CustomFieldType $type, mixed $raw): string|bool
    {
        // Reject an absent/empty filter value for EVERY type. Checkbox is exempt:
        // there false and '0' are meaningful and validated by boolean().
        if ($raw === null) {
            throw CustomFieldConflict::make('Filter value is required.');
        }
        if ($type !== CustomFieldType::Checkbox && is_scalar($raw) && trim((string) $raw) === '') {
            throw CustomFieldConflict::make('Filter value must not be empty.');
        }

        return match ($type) {
            CustomFieldType::Number => $this->decimal($raw),
            CustomFieldType::Checkbox => $this->boolean($raw),
            CustomFieldType::Text => $this->text($raw),
            CustomFieldType::Select => $this->boundedString($raw), // membership skipped for filters
            CustomFieldType::Url => $this->url($raw),               // full URL validity
            CustomFieldType::Email => $this->email($raw),           // full email validity
        };
    }

    private function isEmpty(mixed $raw, CustomFieldType $type): bool
    {
        if ($raw === null) {
            return true;
        }
        // checkbox false is a meaningful value, not "empty".
        if ($type === CustomFieldType::Checkbox) {
            return $raw === '';
        }

        // Scalars and Stringables are "empty" when they trim to "".
        if (is_scalar($raw) || $raw instanceof \Stringable) {
            return trim((string) $raw) === '';
        }

        return false;
    }

    private function decimal(mixed $raw): string
    {
        if (! is_scalar($raw)) {
            throw CustomFieldConflict::make('Number value must be scalar.');
        }
        $s = trim((string) $raw);

        // Reject anything but an optionally-signed decimal; no thousands separators.
        if (! preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            throw CustomFieldConflict::make('Invalid number format.');
        }

        [$intPart, $fracPart] = array_pad(explode('.', ltrim($s, '-'), 2), 2, '');
        $intPart = ltrim($intPart, '0');

        if (strlen($intPart) > self::DECIMAL_INT_DIGITS) {
            throw CustomFieldConflict::make('Number exceeds 16 integer digits.');
        }
        if (strlen($fracPart) > self::DECIMAL_FRAC_DIGITS) {
            // Excess precision is rejected, never silently truncated/rounded.
            throw CustomFieldConflict::make('Number exceeds 4 decimal places.');
        }

        return $s;
    }

    private function boolean(mixed $raw): bool
    {
        $b = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($b === null) {
            throw CustomFieldConflict::make('Invalid boolean value.');
        }

        return $b;
    }

    private function select(mixed $raw, ?array $options): string
    {
        $value = $this->boundedString($raw);
        $allowed = array_map(fn ($o) => (string) ($o['value'] ?? ''), $options ?? []);

        if (! in_array($value, $allowed, true)) {
            throw CustomFieldConflict::make('Value is not an allowed select option.');
        }

        return $value;
    }

    private function text(mixed $raw): string
    {
        // Text must be scalar or Stringable — never let an array become "Array".
        if (! is_scalar($raw) && ! $raw instanceof \Stringable) {
            throw CustomFieldConflict::make('Text value must be a scalar string.');
        }

        return (string) $raw;
    }

    private function url(mixed $raw): string
    {
        $value = $this->boundedString($raw);
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw CustomFieldConflict::make('Invalid URL.');
        }
        // Restrict schemes to http/https before any UI renders these values.
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw CustomFieldConflict::make('URL must use http or https.');
        }

        return $value;
    }

    private function email(mixed $raw): string
    {
        $value = $this->boundedString($raw);
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw CustomFieldConflict::make('Invalid email.');
        }

        return $value;
    }

    private function boundedString(mixed $raw): string
    {
        if (! is_scalar($raw)) {
            throw CustomFieldConflict::make('Value must be a string.');
        }
        $s = trim((string) $raw);
        if (mb_strlen($s) > self::STRING_MAX) {
            throw CustomFieldConflict::make('Value exceeds 255 characters.');
        }

        return $s;
    }
}
