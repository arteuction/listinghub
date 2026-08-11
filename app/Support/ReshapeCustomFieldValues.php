<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Reshapes custom_field_values from polymorphic to typed with a fail-safe,
 * resumable swap (docs/DECLARATIVE_FIELDS.md §2A, §5.1).
 *
 * MySQL/MariaDB autocommit DDL, so a failed multi-step migration cannot roll
 * back. The live table therefore stays CANONICAL and untouched until a fully
 * built and validated `_next` replaces it in a single atomic RENAME. Because a
 * crash can land BETWEEN the swap and the migration record, run() first detects
 * the current on-disk state and resumes safely rather than blindly cleaning up.
 */
final class ReshapeCustomFieldValues
{
    public function run(string $connection): void
    {
        $schema = Schema::connection($connection);
        $db = DB::connection($connection);

        $liveExists = $schema->hasTable('custom_field_values');
        $liveTyped = $liveExists && $schema->hasColumn('custom_field_values', 'value_text');
        $liveLegacy = $liveExists && $schema->hasColumn('custom_field_values', 'valuable_type');
        $hasOld = $schema->hasTable('custom_field_values_old');
        $hasNext = $schema->hasTable('custom_field_values_next');

        // --- State detection (resume-safe) --------------------------------
        if ($liveTyped) {
            // The swap already happened. Finish idempotently: verify the typed
            // table, drop the superseded/leftover temporaries. Never backfill.
            $this->assertTypedShape($schema);
            $this->ensureFlags($schema);
            $schema->dropIfExists('custom_field_values_next');
            $schema->dropIfExists('custom_field_values_old');

            return; // idempotent success (covers "typed + _old" and "typed, no _old")
        }

        if (! $liveLegacy) {
            throw new RuntimeException('Unrecognised custom_field_values state; aborting for safety.');
        }

        // Live is legacy. A stray _old alongside a legacy live table is an
        // unknown/corrupt combination — refuse rather than guess.
        if ($hasOld) {
            throw new RuntimeException('Legacy live table with a stray _old table; aborting for safety.');
        }

        // A leftover partial _next from a failed build is safe to discard.
        if ($hasNext) {
            $schema->dropIfExists('custom_field_values_next');
        }

        // --- Fresh build ---------------------------------------------------
        $this->ensureFlags($schema);
        $this->createNext($schema);

        // Validate + copy into _next. On ANY bad row this throws; the live
        // (legacy) table is still intact and a retry restarts cleanly.
        (new CustomFieldBackfill)->run($db, 'custom_field_values', 'custom_field_values_next');

        // Atomic swap — only now does the live table change.
        if (in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) {
            $db->statement('RENAME TABLE custom_field_values TO custom_field_values_old, custom_field_values_next TO custom_field_values');
        } else {
            $db->transaction(function () use ($schema): void {
                $schema->rename('custom_field_values', 'custom_field_values_old');
                $schema->rename('custom_field_values_next', 'custom_field_values');
            });
        }

        $schema->dropIfExists('custom_field_values_old');
    }

    private function ensureFlags(\Illuminate\Database\Schema\Builder $schema): void
    {
        if (! $schema->hasColumn('custom_fields', 'searchable')) {
            $schema->table('custom_fields', function (Blueprint $t): void {
                $t->boolean('searchable')->default(false);
                $t->boolean('filterable')->default(false);
                $t->boolean('sortable')->default(false);
            });
        }
    }

    private function createNext(\Illuminate\Database\Schema\Builder $schema): void
    {
        $schema->create('custom_field_values_next', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('custom_field_id');
            $t->unsignedBigInteger('listing_id');
            $t->foreign('custom_field_id', 'cfv_typed_field_fk')->references('id')->on('custom_fields')->cascadeOnDelete();
            $t->foreign('listing_id', 'cfv_typed_listing_fk')->references('id')->on('listings')->cascadeOnDelete();
            $t->text('value_text')->nullable();
            $t->string('value_string', 255)->nullable();
            $t->decimal('value_decimal', 20, 4)->nullable();
            $t->boolean('value_boolean')->nullable();
            $t->timestamps();
            $t->unique(['custom_field_id', 'listing_id'], 'cfv_typed_unique');
            $t->index(['custom_field_id', 'value_string'], 'cfv_typed_string_idx');
            $t->index(['custom_field_id', 'value_decimal'], 'cfv_typed_decimal_idx');
            $t->index(['custom_field_id', 'value_boolean'], 'cfv_typed_boolean_idx');
        });
    }

    private function assertTypedShape(\Illuminate\Database\Schema\Builder $schema): void
    {
        foreach (['custom_field_id', 'listing_id', 'value_text', 'value_string', 'value_decimal', 'value_boolean'] as $col) {
            if (! $schema->hasColumn('custom_field_values', $col)) {
                throw new RuntimeException("Typed custom_field_values is missing column {$col}; aborting.");
            }
        }
    }
}
