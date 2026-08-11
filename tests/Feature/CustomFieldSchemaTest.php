<?php

declare(strict_types=1);

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Listing;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('has the typed value columns and no legacy polymorphic columns', function () {
    foreach (['custom_field_id', 'listing_id', 'value_text', 'value_string', 'value_decimal', 'value_boolean'] as $col) {
        expect(Schema::hasColumn('custom_field_values', $col))->toBeTrue("missing {$col}");
    }
    expect(Schema::hasColumn('custom_field_values', 'valuable_id'))->toBeFalse();
    expect(Schema::hasColumn('custom_field_values', 'valuable_type'))->toBeFalse();
});

it('exposes searchable/filterable/sortable flags on custom_fields', function () {
    foreach (['searchable', 'filterable', 'sortable'] as $col) {
        expect(Schema::hasColumn('custom_fields', $col))->toBeTrue("missing {$col}");
    }
});

it('dropped the legacy backfill table', function () {
    expect(Schema::hasTable('custom_field_values_legacy'))->toBeFalse();
});

it('enforces one value per (custom_field_id, listing_id)', function () {
    $field = CustomField::factory()->create();
    $listing = Listing::factory()->create();

    CustomFieldValue::factory()->create(['custom_field_id' => $field->id, 'listing_id' => $listing->id]);

    CustomFieldValue::factory()->create(['custom_field_id' => $field->id, 'listing_id' => $listing->id]);
})->throws(QueryException::class);

it('enforces the custom_field and listing foreign keys', function () {
    CustomFieldValue::factory()->create(['custom_field_id' => 999999, 'listing_id' => 999999]);
})->throws(QueryException::class);
