<?php

declare(strict_types=1);

namespace App\Actions\CustomFields;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Listing;
use App\Support\CustomFieldValueNormalizer;
use Illuminate\Support\Facades\DB;

final class SetFormFieldValue
{
    public function __construct(
        private readonly CustomFieldValueNormalizer $normalizer,
    ) {}

    public function handle(CustomField $field, Listing $listing, mixed $rawValue): ?CustomFieldValue
    {
        $field->loadMissing('category');

        $normalized = $this->normalizer->normalize(
            $field->type,
            $rawValue,
            $field->options,
        );

        return DB::transaction(function () use ($field, $listing, $normalized): ?CustomFieldValue {
            $existing = CustomFieldValue::query()
                ->where('custom_field_id', $field->id)
                ->where('listing_id', $listing->id)
                ->lockForUpdate()
                ->first();

            if ($normalized === null) {
                $existing?->delete();

                return null;
            }

            $nullColumns = array_fill_keys(
                ['value_text', 'value_string', 'value_decimal', 'value_boolean'],
                null
            );
            $payload = array_merge($nullColumns, [$normalized['column'] => $normalized['value']]);

            if ($existing) {
                $existing->fill($payload)->save();

                return $existing->fresh();
            }

            return CustomFieldValue::query()->create(array_merge($payload, [
                'custom_field_id' => $field->id,
                'listing_id' => $listing->id,
            ]));
        });
    }
}
