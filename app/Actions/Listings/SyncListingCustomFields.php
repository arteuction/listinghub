<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Enums\CustomFieldType;
use App\Exceptions\CustomFieldConflict;
use App\Models\CustomField;
use App\Models\Listing;
use App\Support\CustomFieldValueNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Full-replacement sync of a listing's custom field values
 * (docs/DECLARATIVE_FIELDS.md §5.2). The payload is the COMPLETE desired state
 * for the listing's category:
 *
 *  - missing optional field      -> existing value deleted
 *  - missing required field      -> whole sync rejected
 *  - null / empty (optional)     -> value deleted
 *  - null / empty (required)     -> rejected
 *  - unknown / cross-category key -> rejected
 *
 * Validation is fully resolved BEFORE any write (two-phase), inside one
 * transaction with the listing row locked. One error => zero changes. Applying
 * the same payload twice yields the same state.
 */
class SyncListingCustomFields
{
    public function __construct(private readonly CustomFieldValueNormalizer $normalizer) {}

    /**
     * @param  array<string, mixed>  $payload  field key => raw value
     */
    public function handle(Listing $listing, array $payload): void
    {
        DB::transaction(function () use ($listing, $payload): void {
            /** @var Listing $locked */
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());

            // Lock the definition rows too: a concurrent UpdateCustomField
            // (which also locks the row) cannot change a field's type/options
            // between our validation and our write.
            /** @var array<string, CustomField> $fields */
            $fields = CustomField::query()
                ->where('category_id', $locked->category_id)
                ->lockForUpdate()
                ->get()
                ->keyBy('key')
                ->all();

            // Reject any key that is unknown or belongs to another category.
            foreach (array_keys($payload) as $key) {
                if (! isset($fields[$key])) {
                    throw CustomFieldConflict::forField((string) $key, "Unknown or cross-category field: {$key}.");
                }
            }

            // Phase 1 — resolve every field to a concrete op, no writes yet.
            $ops = [];
            foreach ($fields as $key => $field) {
                /** @var CustomFieldType $type */
                $type = $field->type;
                $present = array_key_exists($key, $payload);
                try {
                    $normalized = $present
                        ? $this->normalizer->normalize($type, $payload[$key], $field->options)
                        : null;
                } catch (CustomFieldConflict $e) {
                    // Re-throw as a per-field error so the form can target it.
                    throw CustomFieldConflict::forField((string) $key, $e->getMessage());
                }

                if ($normalized === null) {
                    // absent, or explicitly empty
                    if ($field->is_required) {
                        throw CustomFieldConflict::forField((string) $key, "Required field is missing or empty: {$key}.");
                    }
                    $ops[] = ['delete', $field->id];

                    continue;
                }

                $ops[] = ['set', $field->id, $normalized['column'], $normalized['value']];
            }

            // Full replacement across the WHOLE listing: drop any value whose
            // field is not in the current category (e.g. left over after a
            // category change) — controlled removal, never a manual delete.
            $currentFieldIds = array_map(static fn (CustomField $f): int => (int) $f->id, $fields);
            $locked->customFieldValues()
                ->when($currentFieldIds !== [], fn ($q) => $q->whereNotIn('custom_field_id', $currentFieldIds))
                ->delete();

            // Phase 2 — apply.
            foreach ($ops as $op) {
                if ($op[0] === 'delete') {
                    $locked->customFieldValues()->where('custom_field_id', $op[1])->delete();

                    continue;
                }

                [, $fieldId, $column, $value] = $op;
                $locked->customFieldValues()->updateOrCreate(
                    ['custom_field_id' => $fieldId],
                    [
                        'value_text' => null,
                        'value_string' => null,
                        'value_decimal' => null,
                        'value_boolean' => null,
                        $column => $value,
                    ],
                );
            }
        });
    }
}
