<?php

declare(strict_types=1);

use App\Enums\CustomFieldType;
use App\Exceptions\InvalidSearchQuery;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Listing;
use App\Services\Search\ListingSearchQuery;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->q = new ListingSearchQuery;
    $this->cat = Category::factory()->create();
});

function fld(Category $cat, CustomFieldType $type, array $extra = []): CustomField
{
    return CustomField::factory()->create(array_merge([
        'category_id' => $cat->id,
        'type' => $type,
        'filterable' => true,
    ], $extra));
}

function lst(Category $cat): Listing
{
    return Listing::factory()->create(['category_id' => $cat->id]);
}

function val(CustomField $f, Listing $l, string $column, mixed $value): void
{
    CustomFieldValue::create(['custom_field_id' => $f->id, 'listing_id' => $l->id, $column => $value]);
}

function ids($builder): array
{
    return $builder->pluck('listings.id')->all();
}

it('filters select with eq and in', function () {
    $field = fld($this->cat, CustomFieldType::Select, ['key' => 'color', 'options' => [['value' => 'red'], ['value' => 'blue'], ['value' => 'green']]]);
    $a = lst($this->cat);
    $b = lst($this->cat);
    $c = lst($this->cat);
    val($field, $a, 'value_string', 'red');
    val($field, $b, 'value_string', 'blue');
    val($field, $c, 'value_string', 'green');

    $eq = ids($this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'color', 'operator' => 'eq', 'value' => 'red']]]));
    expect($eq)->toBe([$a->id]);

    $in = ids($this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'color', 'operator' => 'in', 'values' => ['red', 'green']]]]));
    expect($in)->toEqualCanonicalizing([$a->id, $c->id]);
});

it('filters number with comparison and between', function () {
    $field = fld($this->cat, CustomFieldType::Number, ['key' => 'area']);
    $a = lst($this->cat);
    $b = lst($this->cat);
    $c = lst($this->cat);
    val($field, $a, 'value_decimal', '10');
    val($field, $b, 'value_decimal', '15');
    val($field, $c, 'value_decimal', '25');

    expect(ids($this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'area', 'operator' => 'gte', 'value' => '15']]])))
        ->toEqualCanonicalizing([$b->id, $c->id]);
    expect(ids($this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'area', 'operator' => 'between', 'value' => ['12', '20']]]])))
        ->toBe([$b->id]);
});

it('filters text with contains, treating % and _ as literals', function () {
    $field = fld($this->cat, CustomFieldType::Text, ['key' => 'note']);
    $a = lst($this->cat);
    $b = lst($this->cat);
    val($field, $a, 'value_text', 'discount 50% today');
    val($field, $b, 'value_text', 'x50y');

    // '50%' must match the literal, not the wildcard-y 'x50y'.
    expect(ids($this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'note', 'operator' => 'contains', 'value' => '50%']]])))
        ->toBe([$a->id]);
});

it('does not duplicate a listing that matches multiple filters', function () {
    $color = fld($this->cat, CustomFieldType::Select, ['key' => 'color', 'options' => [['value' => 'red']]]);
    $area = fld($this->cat, CustomFieldType::Number, ['key' => 'area']);
    $l = lst($this->cat);
    val($color, $l, 'value_string', 'red');
    val($area, $l, 'value_decimal', '5');

    $res = ids($this->q->build(['category_id' => $this->cat->id, 'filters' => [
        ['key' => 'color', 'operator' => 'eq', 'value' => 'red'],
        ['key' => 'area', 'operator' => 'gte', 'value' => '5'],
    ]]));

    expect($res)->toBe([$l->id]); // exactly once
});

it('sorts with NULLs last and a deterministic id tie-breaker', function () {
    $field = fld($this->cat, CustomFieldType::Number, ['key' => 'area', 'sortable' => true]);
    $a = lst($this->cat);
    $b = lst($this->cat);
    $none = lst($this->cat);   // no value -> NULL
    $c = lst($this->cat);
    val($field, $a, 'value_decimal', '30');
    val($field, $b, 'value_decimal', '10');
    val($field, $c, 'value_decimal', '20');

    $asc = ids($this->q->build(['category_id' => $this->cat->id, 'sort' => ['key' => 'area', 'direction' => 'asc']]));
    expect($asc)->toBe([$b->id, $c->id, $a->id, $none->id]); // NULL last

    $desc = ids($this->q->build(['category_id' => $this->cat->id, 'sort' => ['key' => 'area', 'direction' => 'desc']]));
    expect($desc)->toBe([$a->id, $c->id, $b->id, $none->id]); // NULL still last
});

it('rejects unknown / non-filterable / cross-category fields and bad operators', function () {
    $ok = fld($this->cat, CustomFieldType::Select, ['key' => 'color', 'options' => [['value' => 'red']]]);
    $nonFilter = fld($this->cat, CustomFieldType::Text, ['key' => 'note', 'filterable' => false]);
    $otherCat = Category::factory()->create();
    fld($otherCat, CustomFieldType::Text, ['key' => 'foreign']);

    $bad = fn (array $req) => expect(fn () => $this->q->build($req))->toThrow(InvalidSearchQuery::class);

    $bad(['category_id' => $this->cat->id, 'filters' => [['key' => 'nope', 'operator' => 'eq', 'value' => 'x']]]);
    $bad(['category_id' => $this->cat->id, 'filters' => [['key' => 'note', 'operator' => 'contains', 'value' => 'x']]]); // non-filterable
    $bad(['category_id' => $this->cat->id, 'filters' => [['key' => 'foreign', 'operator' => 'eq', 'value' => 'x']]]);  // cross-category
    $bad(['category_id' => $this->cat->id, 'filters' => [['key' => 'color', 'operator' => 'contains', 'value' => 'x']]]); // op not allowed for select
    $bad(['filters' => [['key' => 'color', 'operator' => 'eq', 'value' => 'x']]]); // missing category_id
});

it('rejects too many filters and duplicate keys', function () {
    $f = fld($this->cat, CustomFieldType::Number, ['key' => 'area']);

    $nine = array_fill(0, 9, ['key' => 'area', 'operator' => 'gte', 'value' => '1']);
    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'filters' => $nine]))->toThrow(InvalidSearchQuery::class);

    $dup = [['key' => 'area', 'operator' => 'gte', 'value' => '1'], ['key' => 'area', 'operator' => 'lte', 'value' => '9']];
    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'filters' => $dup]))->toThrow(InvalidSearchQuery::class);
});

it('never injects request-supplied column / operator / direction', function () {
    fld($this->cat, CustomFieldType::Number, ['key' => 'area', 'sortable' => true]);

    // Malicious key/operator/direction are rejected, never interpolated.
    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'area) OR 1=1 --', 'operator' => 'gte', 'value' => '1']]]))
        ->toThrow(InvalidSearchQuery::class);
    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'area', 'operator' => '; DROP TABLE listings', 'value' => '1']]]))
        ->toThrow(InvalidSearchQuery::class);
    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'sort' => ['key' => 'area', 'direction' => 'asc; DROP TABLE listings']]))
        ->toThrow(InvalidSearchQuery::class);
});

it('rejects an invalid value cast', function () {
    fld($this->cat, CustomFieldType::Number, ['key' => 'area']);
    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'area', 'operator' => 'eq', 'value' => 'not-a-number']]]))
        ->toThrow(InvalidSearchQuery::class);
});

it('plans a filter+sort query without a Cartesian join and uses the index (MySQL)', function () {
    if (config('database.default') === 'sqlite') {
        expect(true)->toBeTrue(); // EXPLAIN shape differs; correctness covered above

        return;
    }

    $field = fld($this->cat, CustomFieldType::Number, ['key' => 'area', 'sortable' => true]);
    $l = lst($this->cat);
    val($field, $l, 'value_decimal', '5');

    $builder = $this->q->build([
        'category_id' => $this->cat->id,
        'filters' => [['key' => 'area', 'operator' => 'gte', 'value' => '1']],
        'sort' => ['key' => 'area', 'direction' => 'asc'],
    ]);

    $rows = DB::select('EXPLAIN '.$builder->toSql(), $builder->getBindings());

    expect($rows)->not->toBeEmpty(); // planning succeeded
    // The correlated subquery on custom_field_values should use an index.
    $usesIndex = collect($rows)->contains(fn ($r) => ($r->table ?? '') === 'custom_field_values' && ! empty($r->key));
    expect($usesIndex)->toBeTrue();
    // No full cross join: no row should be a CROSS JOIN with join buffer over all rows.
    $cartesian = collect($rows)->contains(fn ($r) => str_contains((string) ($r->Extra ?? ''), 'Using join buffer'));
    expect($cartesian)->toBeFalse();
});

it('scopes results to the category, including sort-only queries (P0)', function () {
    $other = Category::factory()->create();
    $field = fld($this->cat, CustomFieldType::Number, ['key' => 'area', 'sortable' => true]);

    $mine = lst($this->cat);
    val($field, $mine, 'value_decimal', '5');
    $foreign = lst($other); // different category, no value for this field

    // sort-only: foreign listings must NOT leak in (they used to trail as NULLs).
    $res = ids($this->q->build(['category_id' => $this->cat->id, 'sort' => ['key' => 'area', 'direction' => 'asc']]));
    expect($res)->toBe([$mine->id])
        ->and($res)->not->toContain($foreign->id);
});

it('returns only the category when given category_id without filters or sort', function () {
    $other = Category::factory()->create();
    $a = lst($this->cat);
    $b = lst($this->cat);
    $foreign = lst($other);

    $res = ids($this->q->build(['category_id' => $this->cat->id]));
    expect($res)->toEqualCanonicalizing([$a->id, $b->id])
        ->and($res)->not->toContain($foreign->id);
});

it('allows an unscoped base query when nothing is provided', function () {
    $other = Category::factory()->create();
    $a = lst($this->cat);
    $b = lst($other);

    $res = ids($this->q->build([]));
    expect($res)->toEqualCanonicalizing([$a->id, $b->id]); // both categories
});

it('rejects zero or negative category_id', function () {
    fld($this->cat, CustomFieldType::Number, ['key' => 'area']);
    foreach ([0, -1, '0'] as $bad) {
        expect(fn () => $this->q->build(['category_id' => $bad, 'filters' => [['key' => 'area', 'operator' => 'gte', 'value' => '1']]]))
            ->toThrow(InvalidSearchQuery::class);
    }
});

it('rejects empty filter values but keeps checkbox false/0 valid', function () {
    $text = fld($this->cat, CustomFieldType::Text, ['key' => 'note']);
    $check = fld($this->cat, CustomFieldType::Checkbox, ['key' => 'flag']);
    $l = lst($this->cat);
    val($check, $l, 'value_boolean', false);

    // empty contains / empty eq -> reject
    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'note', 'operator' => 'contains', 'value' => '']]]))
        ->toThrow(InvalidSearchQuery::class);

    // checkbox false is valid and matches
    expect(ids($this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'flag', 'operator' => 'eq', 'value' => false]]])))
        ->toBe([$l->id]);
    expect(ids($this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'flag', 'operator' => 'eq', 'value' => '0']]])))
        ->toBe([$l->id]);
});

it('validates url and email filter values', function () {
    $url = fld($this->cat, CustomFieldType::Url, ['key' => 'site']);
    $email = fld($this->cat, CustomFieldType::Email, ['key' => 'mail']);

    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'site', 'operator' => 'eq', 'value' => 'not-a-url']]]))
        ->toThrow(InvalidSearchQuery::class);
    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'mail', 'operator' => 'eq', 'value' => 'nope']]]))
        ->toThrow(InvalidSearchQuery::class);
});

it('treats %, _ and ! as literals in contains', function () {
    $field = fld($this->cat, CustomFieldType::Text, ['key' => 'note']);
    $pct = lst($this->cat);
    $under = lst($this->cat);
    $bang = lst($this->cat);
    $decoy = lst($this->cat);
    val($field, $pct, 'value_text', 'a%b');
    val($field, $under, 'value_text', 'a_b');
    val($field, $bang, 'value_text', 'a!b');
    val($field, $decoy, 'value_text', 'axb');

    expect(ids($this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'note', 'operator' => 'contains', 'value' => 'a%b']]])))->toBe([$pct->id]);
    expect(ids($this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'note', 'operator' => 'contains', 'value' => 'a_b']]])))->toBe([$under->id]);
    expect(ids($this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'note', 'operator' => 'contains', 'value' => 'a!b']]])))->toBe([$bang->id]);
});

it('rejects an oversized IN list and malformed request shapes', function () {
    fld($this->cat, CustomFieldType::Select, ['key' => 'color', 'options' => [['value' => 'x']]]);
    fld($this->cat, CustomFieldType::Number, ['key' => 'area', 'sortable' => true]);

    // > MAX_IN_VALUES
    $big = array_map(fn ($i) => (string) $i, range(1, 51));
    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'color', 'operator' => 'in', 'values' => $big]]]))
        ->toThrow(InvalidSearchQuery::class);

    // filters not a list
    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'filters' => ['x' => 1]]))->toThrow(InvalidSearchQuery::class);
    // eq missing value
    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'filters' => [['key' => 'area', 'operator' => 'eq']]]))->toThrow(InvalidSearchQuery::class);
    // direction not a string
    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'sort' => ['key' => 'area', 'direction' => ['asc']]]))->toThrow(InvalidSearchQuery::class);
    // sort not null/array
    expect(fn () => $this->q->build(['category_id' => $this->cat->id, 'sort' => 'area']))->toThrow(InvalidSearchQuery::class);
});
