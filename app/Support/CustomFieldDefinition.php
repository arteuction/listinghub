<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CustomFieldType;
use App\Exceptions\CustomFieldConflict;

/**
 * The single source of truth for definition-level invariants shared by
 * CreateCustomField and UpdateCustomField (docs/DECLARATIVE_FIELDS.md I10, I11):
 * flags must match the type's capability matrix, and options are canonical for
 * select / absent for everything else.
 */
final class CustomFieldDefinition
{
    /** I10 — capability flags must match the type. */
    public static function assertFlags(CustomFieldType $type, bool $searchable, bool $filterable, bool $sortable): void
    {
        if ($searchable && ! $type->isSearchable()) {
            throw CustomFieldConflict::make("Type '{$type->value}' does not support search.");
        }
        if ($sortable && ! $type->isSortable()) {
            throw CustomFieldConflict::make("Type '{$type->value}' does not support sorting.");
        }
        if ($filterable && ! $type->isFilterable()) {
            throw CustomFieldConflict::make("Type '{$type->value}' does not support filtering.");
        }
    }

    /**
     * I11 — return the options to persist, or throw. Non-select must be exactly
     * null (an explicit non-null, incl. [], is rejected). Select requires ≥1
     * option, each an object with a unique, canonical (no surrounding
     * whitespace), non-empty `value` ≤255.
     *
     * @return array<int, array{value:string, label?:string}>|null
     */
    public static function normalizeOptions(CustomFieldType $type, mixed $options): ?array
    {
        if ($type !== CustomFieldType::Select) {
            if ($options !== null) {
                throw CustomFieldConflict::make('Only select fields may define options.');
            }

            return null;
        }

        if (! is_array($options) || $options === []) {
            throw CustomFieldConflict::make('A select field requires at least one option.');
        }

        $values = [];
        foreach ($options as $option) {
            if (! is_array($option)) {
                throw CustomFieldConflict::make('Each select option must be an object.');
            }
            $raw = $option['value'] ?? null;
            if (! is_string($raw)) {
                throw CustomFieldConflict::make('Select option value must be a string.');
            }
            if ($raw !== trim($raw)) {
                throw CustomFieldConflict::make('Select option values must not contain surrounding whitespace.');
            }
            if ($raw === '' || mb_strlen($raw) > 255) {
                throw CustomFieldConflict::make('Select option values must be non-empty and <= 255 chars.');
            }
            if (in_array($raw, $values, true)) {
                throw CustomFieldConflict::make("Duplicate select option value: {$raw}.");
            }
            $values[] = $raw;
        }

        return $options;
    }
}
