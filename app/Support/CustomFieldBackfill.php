<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CustomFieldType;
use App\Models\Listing;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Lossless, validating copy of legacy polymorphic rows into the typed table
 * (docs/DECLARATIVE_FIELDS.md §5.1). Copies into a TARGET table (never the live
 * one) so the caller can validate before an atomic swap. Preserves id,
 * created_at and updated_at. Aborts (throws) — leaving the source untouched —
 * on any unsupported/invalid/orphan/duplicate/cross-category/select row.
 */
final class CustomFieldBackfill
{
    public function __construct(private readonly CustomFieldValueNormalizer $normalizer = new CustomFieldValueNormalizer) {}

    /** @return int number of rows written to $target */
    public function run(
        ConnectionInterface $db,
        string $source = 'custom_field_values',
        string $target = 'custom_field_values_next',
        string $listingClass = Listing::class,
    ): int {
        $fields = $db->table('custom_fields')->get()->keyBy('id');
        $listings = $db->table('listings')->get()->keyBy('id');

        $seen = [];
        $inserted = 0;

        foreach ($db->table($source)->orderBy('id')->cursor() as $row) {
            if ((string) $row->valuable_type !== $listingClass) {
                throw new RuntimeException("Legacy row {$row->id}: unsupported valuable_type.");
            }

            $fieldId = (int) $row->custom_field_id;
            $listingId = (int) $row->valuable_id;

            if (! $fields->has($fieldId)) {
                throw new RuntimeException("Legacy row {$row->id}: missing custom_field.");
            }
            if (! $listings->has($listingId)) {
                throw new RuntimeException("Legacy row {$row->id}: missing listing.");
            }

            $field = $fields->get($fieldId);
            $listing = $listings->get($listingId);

            // Category invariant: the field must belong to the listing's category.
            if ((int) $field->category_id !== (int) $listing->category_id) {
                throw new RuntimeException("Legacy row {$row->id}: field/listing category mismatch.");
            }

            $type = CustomFieldType::from((string) $field->type);
            $options = $field->options ? (json_decode((string) $field->options, true) ?: []) : [];

            $normalized = $this->normalizer->normalize($type, $row->value, $options);
            if ($normalized === null) {
                continue; // empty legacy value: not migrated, not counted
            }

            $key = $fieldId.':'.$listingId;
            if (isset($seen[$key])) {
                throw new RuntimeException("Legacy duplicate for field {$fieldId}, listing {$listingId}.");
            }
            $seen[$key] = true;

            $db->table($target)->insert([
                'id' => (int) $row->id,                 // preserve identity
                'custom_field_id' => $fieldId,
                'listing_id' => $listingId,
                $normalized['column'] => $normalized['value'],
                // Preserve timestamps verbatim — including NULL (truly lossless).
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $inserted++;
        }

        return $inserted;
    }
}
