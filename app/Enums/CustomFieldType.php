<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The six declarative field types. This enum is the single source of truth for
 * the typed matrix: which storage column a value lives in, and which filter
 * operators / search / sort a type supports (docs/DECLARATIVE_FIELDS.md §1).
 */
enum CustomFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case Url = 'url';
    case Email = 'email';

    /** Typed storage column for this type. */
    public function valueColumn(): string
    {
        return match ($this) {
            self::Text => 'value_text',
            self::Number => 'value_decimal',
            self::Select, self::Url, self::Email => 'value_string',
            self::Checkbox => 'value_boolean',
        };
    }

    public function isSearchable(): bool
    {
        return $this === self::Text;
    }

    public function isSortable(): bool
    {
        return in_array($this, [self::Number, self::Select], true);
    }

    /** @return list<string> allowed filter operators for this type */
    public function filterOperators(): array
    {
        return match ($this) {
            self::Text => ['contains'],
            self::Number => ['eq', 'lt', 'lte', 'gt', 'gte', 'between'],
            self::Select => ['eq', 'in'],
            self::Checkbox, self::Url, self::Email => ['eq'],
        };
    }

    public function isFilterable(): bool
    {
        return true; // every type has at least one filter operator
    }
}
