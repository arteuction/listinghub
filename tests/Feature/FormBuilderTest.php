<?php

declare(strict_types=1);

use App\Actions\CustomFields\CreateFormSection;
use App\Actions\CustomFields\DeleteFormSection;
use App\Actions\CustomFields\ReorderFormFields;
use App\Actions\CustomFields\SetFormFieldValue;
use App\Actions\CustomFields\UpdateFormSection;
use App\Enums\CustomFieldType;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\FormSection;
use App\Models\Listing;
use App\Support\CustomFieldValueNormalizer;

/*
| 3.6.1 — Form Experience Builder
| Covers: CreateFormSection, UpdateFormSection, DeleteFormSection,
|         ReorderFormFields, SetFormFieldValue.
*/

function makeCategory(string $name = 'Services'): Category
{
    return Category::query()->create(['name' => $name, 'slug' => str($name)->slug()]);
}

function makeSection(Category $category, string $title = 'Details', int $sort = 1000, ?string $description = null): FormSection
{
    return (new CreateFormSection)->handle($category, $title, $description, $sort);
}

function makeField(Category $category, string $key = 'phone', CustomFieldType $type = CustomFieldType::Text, int $sort = 1000): CustomField
{
    return CustomField::query()->create([
        'category_id' => $category->id,
        'label' => $key,
        'key' => $key,
        'type' => $type,
        'sort_order' => $sort,
    ]);
}

// ─── CreateFormSection ────────────────────────────────────────────────────────

it('creates a section belonging to a category', function () {
    $cat = makeCategory();
    $section = (new CreateFormSection)->handle($cat, 'Basic Info', 'Fill in the basics', 2000, true);

    expect($section->category_id)->toBe($cat->id)
        ->and($section->title)->toBe('Basic Info')
        ->and($section->description)->toBe('Fill in the basics')
        ->and($section->sort_order)->toBe(2000)
        ->and($section->is_collapsible)->toBeTrue();
});

it('trims whitespace from title and description on create', function () {
    $section = (new CreateFormSection)->handle(makeCategory(), '  Padded  ', '  desc  ');

    expect($section->title)->toBe('Padded')
        ->and($section->description)->toBe('desc');
});

it('stores null description when not provided', function () {
    $section = (new CreateFormSection)->handle(makeCategory(), 'Solo');

    expect($section->description)->toBeNull();
});

it('clamps negative sort_order to zero on create', function () {
    $section = (new CreateFormSection)->handle(makeCategory(), 'Edge', sortOrder: -5);

    expect($section->sort_order)->toBe(0);
});

// ─── UpdateFormSection ────────────────────────────────────────────────────────

it('updates allowed fields', function () {
    $section = makeSection(makeCategory());

    (new UpdateFormSection)->handle($section, [
        'title' => 'Updated',
        'description' => 'New desc',
        'sort_order' => 500,
        'is_collapsible' => true,
    ]);

    $section->refresh();
    expect($section->title)->toBe('Updated')
        ->and($section->description)->toBe('New desc')
        ->and($section->sort_order)->toBe(500)
        ->and($section->is_collapsible)->toBeTrue();
});

it('rejects unknown fields on update', function () {
    $section = makeSection(makeCategory());

    expect(fn () => (new UpdateFormSection)->handle($section, ['category_id' => 99]))
        ->toThrow(InvalidArgumentException::class, 'Unknown fields');
});

it('rejects empty title on update', function () {
    $section = makeSection(makeCategory());

    expect(fn () => (new UpdateFormSection)->handle($section, ['title' => '  ']))
        ->toThrow(InvalidArgumentException::class, 'Title cannot be empty');
});

it('sets description to null when blank string on update', function () {
    $section = makeSection(makeCategory(), 'Desc Section', 1000, 'old');
    (new UpdateFormSection)->handle($section, ['description' => '  ']);

    expect($section->fresh()->description)->toBeNull();
});

// ─── DeleteFormSection ────────────────────────────────────────────────────────

it('deletes an empty section', function () {
    $section = makeSection(makeCategory());
    $id = $section->id;

    (new DeleteFormSection)->handle($section);

    expect(FormSection::query()->find($id))->toBeNull();
});

it('refuses deletion when section has assigned fields', function () {
    $cat = makeCategory();
    $section = makeSection($cat);
    makeField($cat)->update(['section_id' => $section->id]);

    expect(fn () => (new DeleteFormSection)->handle($section))
        ->toThrow(InvalidArgumentException::class, 'still has assigned fields');
});

// ─── ReorderFormFields ────────────────────────────────────────────────────────

it('reorders fields within a category', function () {
    $cat = makeCategory();
    $a = makeField($cat, 'alpha', sort: 1000);
    $b = makeField($cat, 'beta', sort: 2000);
    $c = makeField($cat, 'gamma', sort: 3000);

    (new ReorderFormFields)->handle([$c->id, $a->id, $b->id], $cat);

    $orders = CustomField::query()
        ->whereIn('id', [$a->id, $b->id, $c->id])
        ->orderBy('sort_order')
        ->pluck('id')
        ->all();

    expect($orders)->toBe([$c->id, $a->id, $b->id]);
});

it('no-ops when the reorder list is empty', function () {
    $cat = makeCategory();
    makeField($cat, 'alpha', sort: 1000);

    (new ReorderFormFields)->handle([], $cat);

    expect(true)->toBeTrue();
});

it('rejects duplicate IDs in reorder list', function () {
    $cat = makeCategory();
    $a = makeField($cat, 'alpha');

    expect(fn () => (new ReorderFormFields)->handle([$a->id, $a->id], $cat))
        ->toThrow(InvalidArgumentException::class, 'duplicate');
});

it('rejects field IDs that do not belong to the category', function () {
    $cat1 = makeCategory('Cat1');
    $cat2 = makeCategory('Cat2');
    $a = makeField($cat1, 'alpha');
    $b = makeField($cat2, 'beta');

    expect(fn () => (new ReorderFormFields)->handle([$a->id, $b->id], $cat1))
        ->toThrow(InvalidArgumentException::class, 'not found in this category');
});

// ─── SetFormFieldValue ────────────────────────────────────────────────────────

function makeSetAction(): SetFormFieldValue
{
    return new SetFormFieldValue(new CustomFieldValueNormalizer);
}

function makeListing(Category $category): Listing
{
    return Listing::factory()->create(['category_id' => $category->id]);
}

it('creates a value row on first set', function () {
    $cat = makeCategory();
    $field = makeField($cat, 'notes', CustomFieldType::Text);
    $listing = makeListing($cat);

    $value = makeSetAction()->handle($field, $listing, 'hello world');

    expect($value)->not->toBeNull()
        ->and($value->value_text)->toBe('hello world')
        ->and($value->custom_field_id)->toBe($field->id)
        ->and($value->listing_id)->toBe($listing->id);
});

it('updates an existing value row', function () {
    $cat = makeCategory();
    $field = makeField($cat, 'notes', CustomFieldType::Text);
    $listing = makeListing($cat);

    makeSetAction()->handle($field, $listing, 'first');
    $updated = makeSetAction()->handle($field, $listing, 'second');

    expect($updated->value_text)->toBe('second')
        ->and(CustomFieldValue::query()->where('custom_field_id', $field->id)->count())->toBe(1);
});

it('deletes an existing row when value is empty', function () {
    $cat = makeCategory();
    $field = makeField($cat, 'notes', CustomFieldType::Text);
    $listing = makeListing($cat);

    makeSetAction()->handle($field, $listing, 'initial');
    $result = makeSetAction()->handle($field, $listing, '');

    expect($result)->toBeNull()
        ->and(CustomFieldValue::query()->where('custom_field_id', $field->id)->exists())->toBeFalse();
});

it('returns null without creating a row when set to empty on fresh field', function () {
    $cat = makeCategory();
    $field = makeField($cat, 'notes', CustomFieldType::Text);
    $listing = makeListing($cat);

    $result = makeSetAction()->handle($field, $listing, null);

    expect($result)->toBeNull()
        ->and(CustomFieldValue::query()->where('custom_field_id', $field->id)->exists())->toBeFalse();
});

it('stores a decimal value and nulls other columns', function () {
    $cat = makeCategory();
    $field = makeField($cat, 'price', CustomFieldType::Number);
    $listing = makeListing($cat);

    $value = makeSetAction()->handle($field, $listing, '12.50');

    expect($value->value_decimal)->toBe('12.5000')
        ->and($value->value_text)->toBeNull()
        ->and($value->value_string)->toBeNull()
        ->and($value->value_boolean)->toBeNull();
});

it('stores a boolean value', function () {
    $cat = makeCategory();
    $field = makeField($cat, 'verified', CustomFieldType::Checkbox);
    $listing = makeListing($cat);

    $value = makeSetAction()->handle($field, $listing, true);

    expect($value->value_boolean)->toBeTrue();
});
