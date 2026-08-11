<?php

declare(strict_types=1);

use App\Support\ReshapeCustomFieldValues;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Exercises the full fail-safe swap on a throwaway connection whose DRIVER
| MATCHES the default one — so on the MariaDB gate this runs the real
| `RENAME TABLE ... , ...` atomic swap, and on SQLite the transactional rename.
*/

const RS_CONN = 'rcfb';
const RS_DB = 'lh_reshape_test';

beforeEach(function () {
    $default = config('database.default');

    if ($default === 'sqlite') {
        Config::set('database.connections.'.RS_CONN, [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
    } else {
        // CREATE/DROP DATABASE must NOT run on the RefreshDatabase-wrapped
        // default connection (DDL implicit-commits it). Use a separate admin
        // connection.
        $base = config('database.connections.'.$default);
        Config::set('database.connections.rcfb_admin', $base);
        DB::purge('rcfb_admin');
        DB::connection('rcfb_admin')->statement('DROP DATABASE IF EXISTS '.RS_DB);
        DB::connection('rcfb_admin')->statement('CREATE DATABASE '.RS_DB.' CHARACTER SET utf8mb4');
        Config::set('database.connections.'.RS_CONN, array_merge($base, ['database' => RS_DB]));
    }
    DB::purge(RS_CONN);

    $s = Schema::connection(RS_CONN);
    foreach (['custom_field_values_old', 'custom_field_values_next', 'custom_field_values', 'custom_fields', 'listings'] as $t) {
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
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        try {
            DB::connection('rcfb_admin')->statement('DROP DATABASE IF EXISTS '.RS_DB);
        } catch (Throwable) {
        }
        DB::purge('rcfb_admin');
    }
    DB::purge(RS_CONN);
});

function rc(): ConnectionInterface
{
    return DB::connection(RS_CONN);
}

function rsSeed(int $listingId = 1, int $fieldId = 1, string $value = 'hello', string $ts = '2021-05-06 07:08:09'): void
{
    rc()->table('listings')->insert(['id' => $listingId, 'category_id' => 1]);
    rc()->table('custom_fields')->insert(['id' => $fieldId, 'category_id' => 1, 'type' => 'text', 'options' => null]);
    rc()->table('custom_field_values')->insert([
        'id' => 500, 'custom_field_id' => $fieldId, 'valuable_id' => $listingId,
        'valuable_type' => 'App\\Models\\Listing', 'value' => $value,
        'created_at' => $ts, 'updated_at' => $ts,
    ]);
}

it('swaps to the typed table and preserves id + timestamps', function () {
    rsSeed();

    (new ReshapeCustomFieldValues)->run(RS_CONN);

    $s = Schema::connection(RS_CONN);
    // typed table is now live; temporaries are gone
    expect($s->hasColumn('custom_field_values', 'value_text'))->toBeTrue();
    expect($s->hasColumn('custom_fields', 'searchable'))->toBeTrue();
    expect($s->hasTable('custom_field_values_next'))->toBeFalse();
    expect($s->hasTable('custom_field_values_old'))->toBeFalse();

    $row = rc()->table('custom_field_values')->first();
    expect((int) $row->id)->toBe(500);                 // identity preserved
    expect($row->value_text)->toBe('hello');
    expect((string) $row->created_at)->toContain('2021-05-06 07:08:09'); // timestamp preserved
});

it('leaves the live table canonical when backfill fails, and a retry succeeds', function () {
    // Two rows; the second is an invalid number -> backfill aborts.
    rc()->table('listings')->insert(['id' => 1, 'category_id' => 1]);
    rc()->table('custom_fields')->insert(['id' => 1, 'category_id' => 1, 'type' => 'text']);
    rc()->table('custom_fields')->insert(['id' => 2, 'category_id' => 1, 'type' => 'number']);
    rc()->table('custom_field_values')->insert([
        ['id' => 1, 'custom_field_id' => 1, 'valuable_id' => 1, 'valuable_type' => 'App\\Models\\Listing', 'value' => 'ok'],
        ['id' => 2, 'custom_field_id' => 2, 'valuable_id' => 1, 'valuable_type' => 'App\\Models\\Listing', 'value' => 'not-a-number'],
    ]);

    // Invalid value aborts backfill (CustomFieldConflict or RuntimeException).
    expect(fn () => (new ReshapeCustomFieldValues)->run(RS_CONN))->toThrow(Exception::class);

    // Live table is STILL the polymorphic one, fully intact & usable.
    $s = Schema::connection(RS_CONN);
    expect($s->hasColumn('custom_field_values', 'valuable_type'))->toBeTrue();
    expect(rc()->table('custom_field_values')->count())->toBe(2);

    // Fix the bad data and retry -> clean success.
    rc()->table('custom_field_values')->where('id', 2)->delete();
    (new ReshapeCustomFieldValues)->run(RS_CONN);

    expect($s->hasColumn('custom_field_values', 'value_text'))->toBeTrue();
    expect(rc()->table('custom_field_values')->count())->toBe(1);
    expect((int) rc()->table('custom_field_values')->value('id'))->toBe(1);
});

it('resumes idempotently when interrupted after the swap, before dropping _old', function () {
    rsSeed(); // legacy row
    (new ReshapeCustomFieldValues)->run(RS_CONN); // -> typed live, no _old

    // Simulate a crash that left a stray _old table behind (post-swap, pre-drop).
    Schema::connection(RS_CONN)->create('custom_field_values_old', fn ($t) => $t->id());

    // A retry must NOT re-backfill the typed live table; it finishes cleanly.
    (new ReshapeCustomFieldValues)->run(RS_CONN);

    $s = Schema::connection(RS_CONN);
    expect($s->hasColumn('custom_field_values', 'value_text'))->toBeTrue()
        ->and($s->hasTable('custom_field_values_old'))->toBeFalse()
        ->and((int) rc()->table('custom_field_values')->value('id'))->toBe(500); // data preserved
});

it('is a no-op when already typed with no _old (interrupted before migration record)', function () {
    rsSeed();
    (new ReshapeCustomFieldValues)->run(RS_CONN); // typed live, no _old
    $before = rc()->table('custom_field_values')->count();

    (new ReshapeCustomFieldValues)->run(RS_CONN); // resume -> idempotent success

    expect(Schema::connection(RS_CONN)->hasColumn('custom_field_values', 'value_text'))->toBeTrue()
        ->and(rc()->table('custom_field_values')->count())->toBe($before);
});

it('refuses an unknown state (legacy live with a stray _old)', function () {
    rsSeed(); // legacy live
    Schema::connection(RS_CONN)->create('custom_field_values_old', fn ($t) => $t->id());

    expect(fn () => (new ReshapeCustomFieldValues)->run(RS_CONN))->toThrow(RuntimeException::class);
});
