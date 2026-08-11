<?php

declare(strict_types=1);

use App\Actions\CustomFields\UpdateCustomField;
use App\Enums\CustomFieldType;
use App\Exceptions\CustomFieldConflict;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Listing;

beforeEach(function () {
    $this->update = app(UpdateCustomField::class);
});

it('allows key/type/label changes while no values exist', function () {
    $f = CustomField::factory()->create(['type' => CustomFieldType::Text, 'key' => 'old']);

    $updated = $this->update->handle($f, ['key' => 'new', 'type' => CustomFieldType::Number, 'label' => 'Area']);

    expect($updated->key)->toBe('new')
        ->and($updated->type)->toBe(CustomFieldType::Number)
        ->and($updated->label)->toBe('Area');
});

it('forbids changing key or type once values exist (I5/I6)', function () {
    $f = CustomField::factory()->create(['type' => CustomFieldType::Text, 'key' => 'bio']);
    CustomFieldValue::factory()->create([
        'custom_field_id' => $f->id,
        'listing_id' => Listing::factory(),
        'value_text' => 'used',
    ]);

    expect(fn () => $this->update->handle($f, ['key' => 'renamed']))->toThrow(CustomFieldConflict::class);
    expect(fn () => $this->update->handle($f, ['type' => CustomFieldType::Number]))->toThrow(CustomFieldConflict::class);

    expect($f->fresh()->key)->toBe('bio'); // unchanged
});

it('lets a select option label change but blocks removing a used value (I7/I8)', function () {
    $f = CustomField::factory()->select(['red', 'blue'])->create();
    CustomFieldValue::factory()->create([
        'custom_field_id' => $f->id,
        'listing_id' => Listing::factory(),
        'value_string' => 'red',
    ]);

    // Relabel 'red' -> ok; value stays 'red'.
    $ok = $this->update->handle($f, ['options' => [
        ['value' => 'red', 'label' => 'Crimson'],
        ['value' => 'blue', 'label' => 'Blue'],
    ]]);
    expect(collect($ok->options)->firstWhere('value', 'red')['label'])->toBe('Crimson');

    // Removing 'red' (in use) -> rejected.
    expect(fn () => $this->update->handle($f, ['options' => [['value' => 'blue', 'label' => 'Blue']]]))
        ->toThrow(CustomFieldConflict::class);

    // Removing an unused option ('blue') -> allowed.
    $ok2 = $this->update->handle($f, ['options' => [['value' => 'red', 'label' => 'Crimson']]]);
    expect(collect($ok2->options)->pluck('value')->all())->toBe(['red']);
});

it('forbids changing category_id once values exist (I9)', function () {
    $f = CustomField::factory()->create(['type' => CustomFieldType::Text]);
    CustomFieldValue::factory()->create([
        'custom_field_id' => $f->id, 'listing_id' => Listing::factory(), 'value_text' => 'used',
    ]);

    expect(fn () => $this->update->handle($f, ['category_id' => 99999]))->toThrow(CustomFieldConflict::class);
    expect((int) $f->fresh()->category_id)->toBe((int) $f->category_id);
});

it('enforces the flag/capability matrix (I10)', function () {
    $text = CustomField::factory()->create(['type' => CustomFieldType::Text]);
    $number = CustomField::factory()->create(['type' => CustomFieldType::Number]);

    // text cannot be sortable; number cannot be searchable
    expect(fn () => $this->update->handle($text, ['sortable' => true]))->toThrow(CustomFieldConflict::class);
    expect(fn () => $this->update->handle($number, ['searchable' => true]))->toThrow(CustomFieldConflict::class);

    // valid combinations pass
    expect($this->update->handle($text, ['searchable' => true])->searchable)->toBeTrue();
    expect($this->update->handle($number, ['sortable' => true])->sortable)->toBeTrue();
});

it('validates select options and forbids options on non-select (I11)', function () {
    $select = CustomField::factory()->create(['type' => CustomFieldType::Select, 'options' => [['value' => 'a', 'label' => 'A']]]);
    $text = CustomField::factory()->create(['type' => CustomFieldType::Text]);

    // duplicate value
    expect(fn () => $this->update->handle($select, ['options' => [['value' => 'x'], ['value' => 'x']]]))
        ->toThrow(CustomFieldConflict::class);
    // empty value
    expect(fn () => $this->update->handle($select, ['options' => [['value' => '']]]))
        ->toThrow(CustomFieldConflict::class);
    // options on a non-select type
    expect(fn () => $this->update->handle($text, ['options' => [['value' => 'a']]]))
        ->toThrow(CustomFieldConflict::class);
});

it('clears stale select options when the type changes to non-select (I11)', function () {
    $f = CustomField::factory()->create([
        'type' => CustomFieldType::Select,
        'options' => [['value' => 'a', 'label' => 'A']],
    ]);

    $updated = $this->update->handle($f, ['type' => CustomFieldType::Number]);

    expect($updated->type)->toBe(CustomFieldType::Number)
        ->and($updated->options)->toBeNull(); // stale options auto-cleared
});

it('rejects empty and malformed select options (I11)', function () {
    $select = CustomField::factory()->create(['type' => CustomFieldType::Select, 'options' => [['value' => 'a']]]);
    $number = CustomField::factory()->create(['type' => CustomFieldType::Number]);

    // options=[] on non-select is not exactly null -> reject
    expect(fn () => $this->update->handle($number, ['options' => []]))->toThrow(CustomFieldConflict::class);
    // whitespace-only value -> reject
    expect(fn () => $this->update->handle($select, ['options' => [['value' => '   ']]]))->toThrow(CustomFieldConflict::class);
    // scalar option element (not an object) -> CustomFieldConflict, not TypeError
    expect(fn () => $this->update->handle($select, ['options' => ['a', 'b']]))->toThrow(CustomFieldConflict::class);
});

it('rejects select option values with surrounding whitespace (I11 canonical codes)', function () {
    $select = CustomField::factory()->create(['type' => CustomFieldType::Select, 'options' => [['value' => 'a']]]);

    // ' red ' would be stored verbatim but normalized to 'red' at read time.
    expect(fn () => $this->update->handle($select, ['options' => [['value' => ' red ']]]))
        ->toThrow(CustomFieldConflict::class);
});
