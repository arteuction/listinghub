<?php

declare(strict_types=1);

use App\Enums\CustomFieldType;
use App\Exceptions\CustomFieldConflict;
use App\Models\Product;
use App\Support\CustomFieldBackfill;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Backfill unit: source = polymorphic `custom_field_values`, target = typed
| `custom_field_values_next`. Run on an ISOLATED sqlite connection ("cfb") so
| no DDL touches the shared suite database.
*/

beforeEach(function () {
    Config::set('database.connections.cfb', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]);
    DB::purge('cfb');
    $s = Schema::connection('cfb');
    foreach (['custom_field_values_next', 'custom_field_values', 'custom_fields', 'listings'] as $t) {
        $s->dropIfExists($t);
    }
    $s->create('listings', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('category_id')->default(1);
    });
    $s->create('custom_fields', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('category_id')->default(1);
        $t->string('type');
        $t->text('options')->nullable();
    });
    $s->create('custom_field_values', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('custom_field_id');
        $t->unsignedBigInteger('valuable_id');
        $t->string('valuable_type');
        $t->text('value')->nullable();
        $t->timestamps();
    });
    $s->create('custom_field_values_next', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('custom_field_id');
        $t->unsignedBigInteger('listing_id');
        $t->text('value_text')->nullable();
        $t->string('value_string', 255)->nullable();
        $t->decimal('value_decimal', 20, 4)->nullable();
        $t->boolean('value_boolean')->nullable();
        $t->timestamps();
    });
});

function cfb(): ConnectionInterface
{
    return DB::connection('cfb');
}

function cfbListing(int $categoryId = 1): int
{
    return (int) cfb()->table('listings')->insertGetId(['category_id' => $categoryId]);
}

function cfbField(CustomFieldType $type, ?array $options = null, int $categoryId = 1): int
{
    return (int) cfb()->table('custom_fields')->insertGetId([
        'category_id' => $categoryId, 'type' => $type->value,
        'options' => $options ? json_encode($options) : null,
    ]);
}

function cfbLegacy(int $fieldId, int $listingId, ?string $value, string $type = 'App\\Models\\Listing', ?string $ts = null): int
{
    return (int) cfb()->table('custom_field_values')->insertGetId([
        'custom_field_id' => $fieldId, 'valuable_id' => $listingId, 'valuable_type' => $type,
        'value' => $value, 'created_at' => $ts, 'updated_at' => $ts,
    ]);
}

function runBackfill(): int
{
    return (new CustomFieldBackfill)->run(cfb(), 'custom_field_values', 'custom_field_values_next');
}

it('backfills all six types losslessly', function () {
    $listing = cfbListing();
    $fields = [
        'text' => cfbField(CustomFieldType::Text),
        'number' => cfbField(CustomFieldType::Number),
        'select' => cfbField(CustomFieldType::Select, [['value' => 'red', 'label' => 'Red']]),
        'checkbox' => cfbField(CustomFieldType::Checkbox),
        'url' => cfbField(CustomFieldType::Url),
        'email' => cfbField(CustomFieldType::Email),
    ];
    cfbLegacy($fields['text'], $listing, 'hello');
    cfbLegacy($fields['number'], $listing, '3.14');
    cfbLegacy($fields['select'], $listing, 'red');
    cfbLegacy($fields['checkbox'], $listing, '1');
    cfbLegacy($fields['url'], $listing, 'https://x.com');
    cfbLegacy($fields['email'], $listing, 'a@b.com');

    expect(runBackfill())->toBe(6);

    $val = fn (int $fid, string $col) => cfb()->table('custom_field_values_next')->where('custom_field_id', $fid)->value($col);
    expect($val($fields['text'], 'value_text'))->toBe('hello');
    expect((float) $val($fields['number'], 'value_decimal'))->toBe(3.14);
    expect($val($fields['select'], 'value_string'))->toBe('red');
    expect((int) $val($fields['checkbox'], 'value_boolean'))->toBe(1);
    expect($val($fields['url'], 'value_string'))->toBe('https://x.com');
});

it('preserves legacy id and timestamps (P1-6)', function () {
    $listing = cfbListing();
    $f = cfbField(CustomFieldType::Text);
    $legacyId = cfbLegacy($f, $listing, 'keep', 'App\\Models\\Listing', '2020-01-02 03:04:05');

    runBackfill();

    $row = cfb()->table('custom_field_values_next')->where('custom_field_id', $f)->first();
    expect((int) $row->id)->toBe($legacyId);
    expect((string) $row->created_at)->toContain('2020-01-02 03:04:05');
});

it('skips empty legacy values without counting them', function () {
    $listing = cfbListing();
    cfbLegacy(cfbField(CustomFieldType::Text), $listing, '');
    expect(runBackfill())->toBe(0);
});

it('aborts on a cross-category legacy row (P1-5)', function () {
    $listing = cfbListing(2);              // listing in category 2
    $f = cfbField(CustomFieldType::Text, null, 1); // field in category 1
    cfbLegacy($f, $listing, 'x');

    expect(fn () => runBackfill())->toThrow(RuntimeException::class);
});

it('aborts on a non-Listing valuable_type', function () {
    $listing = cfbListing();
    cfbLegacy(cfbField(CustomFieldType::Text), $listing, 'x', Product::class);
    expect(fn () => runBackfill())->toThrow(RuntimeException::class);
});

it('aborts on an orphan listing reference', function () {
    cfbLegacy(cfbField(CustomFieldType::Text), 999999, 'x');
    expect(fn () => runBackfill())->toThrow(RuntimeException::class);
});

it('aborts on a duplicate (custom_field_id, listing_id)', function () {
    $listing = cfbListing();
    $f = cfbField(CustomFieldType::Text);
    cfbLegacy($f, $listing, 'a');
    cfbLegacy($f, $listing, 'b');
    expect(fn () => runBackfill())->toThrow(RuntimeException::class);
});

it('aborts on a select value outside its options', function () {
    $listing = cfbListing();
    $f = cfbField(CustomFieldType::Select, [['value' => 'red', 'label' => 'Red']]);
    cfbLegacy($f, $listing, 'green');
    expect(fn () => runBackfill())->toThrow(CustomFieldConflict::class);
});

it('aborts on an orphan custom_field_id', function () {
    $listing = cfbListing();
    cfbLegacy(999999, $listing, 'x'); // custom_field does not exist
    expect(fn () => runBackfill())->toThrow(RuntimeException::class);
});
