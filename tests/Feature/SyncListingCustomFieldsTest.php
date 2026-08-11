<?php

declare(strict_types=1);

use App\Actions\Listings\SyncListingCustomFields;
use App\Enums\CustomFieldType;
use App\Exceptions\CustomFieldConflict;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\Listing;

beforeEach(function () {
    $this->sync = app(SyncListingCustomFields::class);
    $this->category = Category::factory()->create();
    $this->listing = Listing::factory()->create(['category_id' => $this->category->id]);
});

function field(int $categoryId, CustomFieldType $type, string $key, array $extra = []): CustomField
{
    return CustomField::factory()->create(array_merge([
        'category_id' => $categoryId, 'type' => $type, 'key' => $key,
    ], $extra));
}

it('writes each type into its typed column', function () {
    field($this->category->id, CustomFieldType::Text, 'bio');
    field($this->category->id, CustomFieldType::Number, 'area');
    field($this->category->id, CustomFieldType::Checkbox, 'wifi');
    field($this->category->id, CustomFieldType::Select, 'color', ['options' => [['value' => 'red', 'label' => 'Red']]]);

    $this->sync->handle($this->listing, ['bio' => 'hello', 'area' => '12.50', 'wifi' => '1', 'color' => 'red']);

    $rows = $this->listing->customFieldValues()->get()->keyBy('custom_field_id');
    expect($rows)->toHaveCount(4);
    expect($this->listing->customFieldValues()->whereNotNull('value_text')->value('value_text'))->toBe('hello');
    expect($this->listing->customFieldValues()->whereNotNull('value_decimal')->value('value_decimal'))->toBe('12.5000');
    expect((bool) $this->listing->customFieldValues()->whereNotNull('value_boolean')->value('value_boolean'))->toBeTrue();
    expect($this->listing->customFieldValues()->whereNotNull('value_string')->value('value_string'))->toBe('red');
});

it('deletes an optional field omitted from the payload (full replacement)', function () {
    field($this->category->id, CustomFieldType::Text, 'bio');
    $this->sync->handle($this->listing, ['bio' => 'first']);
    expect($this->listing->customFieldValues()->count())->toBe(1);

    // Second sync omits bio -> value removed.
    $this->sync->handle($this->listing, []);
    expect($this->listing->customFieldValues()->count())->toBe(0);
});

it('rejects when a required field is missing or empty, changing nothing', function () {
    field($this->category->id, CustomFieldType::Text, 'bio');
    field($this->category->id, CustomFieldType::Text, 'req', ['is_required' => true]);

    expect(fn () => $this->sync->handle($this->listing, ['bio' => 'x']))
        ->toThrow(CustomFieldConflict::class);

    expect($this->listing->customFieldValues()->count())->toBe(0); // zero change
});

it('rejects unknown or cross-category keys', function () {
    field($this->category->id, CustomFieldType::Text, 'bio');
    $otherField = field(Category::factory()->create()->id, CustomFieldType::Text, 'elsewhere');

    expect(fn () => $this->sync->handle($this->listing, ['bio' => 'x', 'elsewhere' => 'y']))
        ->toThrow(CustomFieldConflict::class);
    expect(fn () => $this->sync->handle($this->listing, ['nope' => 'z']))
        ->toThrow(CustomFieldConflict::class);

    expect($this->listing->customFieldValues()->count())->toBe(0);
});

it('makes one error roll back the whole batch', function () {
    field($this->category->id, CustomFieldType::Text, 'bio');
    field($this->category->id, CustomFieldType::Number, 'area');

    // area is invalid -> nothing (not even bio) is written.
    expect(fn () => $this->sync->handle($this->listing, ['bio' => 'ok', 'area' => 'not-a-number']))
        ->toThrow(CustomFieldConflict::class);

    expect($this->listing->customFieldValues()->count())->toBe(0);
});

it('is idempotent — the same payload yields the same state', function () {
    field($this->category->id, CustomFieldType::Text, 'bio');
    field($this->category->id, CustomFieldType::Number, 'area');

    $payload = ['bio' => 'hello', 'area' => '5.0'];
    $this->sync->handle($this->listing, $payload);
    $first = $this->listing->customFieldValues()->orderBy('custom_field_id')->get()->toArray();

    $this->sync->handle($this->listing, $payload);
    $second = $this->listing->customFieldValues()->orderBy('custom_field_id')->get()->toArray();

    expect($this->listing->customFieldValues()->count())->toBe(2)
        ->and(array_column($second, 'id'))->toBe(array_column($first, 'id')); // same rows, updated in place
});
