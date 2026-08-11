<?php

declare(strict_types=1);

namespace App\Actions\CustomFields;

use App\Enums\CustomFieldType;
use App\Exceptions\CustomFieldConflict;
use App\Models\CustomField;
use App\Support\CustomFieldDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Updates a custom field definition (docs/DECLARATIVE_FIELDS.md I5–I11).
 *  I5  key immutable once values exist
 *  I6  type immutable once values exist
 *  I7/I8 select `value` stable; label may change; a used value cannot be removed
 *  I9  category_id immutable once values exist
 *  I10 flags must match the type capability matrix   (shared CustomFieldDefinition)
 *  I11 select options canonical; non-select options=null (shared CustomFieldDefinition)
 * All checks run before the write; a violation changes nothing.
 */
class UpdateCustomField
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function handle(CustomField $field, array $changes): CustomField
    {
        return DB::transaction(function () use ($field, $changes): CustomField {
            /** @var CustomField $locked */
            $locked = CustomField::query()->lockForUpdate()->findOrFail($field->getKey());

            $hasValues = $locked->values()->exists();

            // I5 / I9 — immutable identity once values exist.
            if ($hasValues && array_key_exists('key', $changes) && $changes['key'] !== $locked->key) {
                throw CustomFieldConflict::make('Cannot change the key of a field that already has values.');
            }
            if ($hasValues && array_key_exists('category_id', $changes)
                && (int) $changes['category_id'] !== (int) $locked->category_id) {
                throw CustomFieldConflict::make('Cannot change the category of a field that already has values.');
            }

            $optionsProvided = array_key_exists('options', $changes);
            $effectiveType = $this->resolveType($locked, $changes, $hasValues); // I6

            // I7 / I8 — select option removal safety (before applying new options).
            if ($optionsProvided && $effectiveType === CustomFieldType::Select) {
                $this->guardRemovedOptions($locked, (array) ($changes['options'] ?? []));
            }

            // I11 — final options per type (auto-clears stale select options on
            // a change to a non-select type). Shared validator.
            $optionsInput = $optionsProvided
                ? $changes['options']
                : ($effectiveType === CustomFieldType::Select ? $locked->options : null);
            $changes['options'] = CustomFieldDefinition::normalizeOptions($effectiveType, $optionsInput);

            // I10 — flags must match the type capability matrix. Shared validator.
            CustomFieldDefinition::assertFlags(
                $effectiveType,
                (bool) (array_key_exists('searchable', $changes) ? $changes['searchable'] : $locked->searchable),
                (bool) (array_key_exists('filterable', $changes) ? $changes['filterable'] : $locked->filterable),
                (bool) (array_key_exists('sortable', $changes) ? $changes['sortable'] : $locked->sortable),
            );

            $locked->fill($changes)->save();

            return $locked;
        });
    }

    /** @param array<string,mixed> $changes */
    private function resolveType(CustomField $locked, array $changes, bool $hasValues): CustomFieldType
    {
        if (! array_key_exists('type', $changes)) {
            return $locked->type;
        }
        $new = $changes['type'] instanceof CustomFieldType
            ? $changes['type']
            : CustomFieldType::from((string) $changes['type']);

        if ($hasValues && $new !== $locked->type) {
            throw CustomFieldConflict::make('Cannot change the type of a field that already has values.');
        }

        return $new;
    }

    /**
     * The caller passes raw request input, so the elements are only *expected*
     * to be option objects — normalizeOptions() is what enforces the shape,
     * and it runs after this guard.
     *
     * @param  array<int, mixed>  $newOptions
     */
    private function guardRemovedOptions(CustomField $field, array $newOptions): void
    {
        // Stored options are not guaranteed to be well formed — a malformed
        // element must reach the CustomFieldConflict path below, never raise a
        // TypeError here. Taking `mixed` keeps that tolerance while giving the
        // analyser a type it can narrow honestly.
        $extractValue = static fn (mixed $option): string => is_array($option) && isset($option['value'])
            ? (string) $option['value']
            : '';

        $newValues = array_map($extractValue, $newOptions);
        $oldValues = array_map($extractValue, (array) ($field->options ?? []));

        foreach (array_diff($oldValues, $newValues) as $value) {
            if ($field->values()->where('value_string', $value)->exists()) {
                throw CustomFieldConflict::make("Option '{$value}' is in use and cannot be removed.");
            }
        }
    }
}
