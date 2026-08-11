<?php

declare(strict_types=1);

use App\Support\ReshapeCustomFieldValues;
use Illuminate\Database\Migrations\Migration;

/**
 * Reshapes custom_field_values from the polymorphic (valuable_*, single value)
 * form into a typed, listing-owned table via a fail-safe swap: the live table
 * stays canonical until a validated `_next` replaces it in one atomic RENAME
 * (docs/DECLARATIVE_FIELDS.md §2A, §5.1). All logic lives in the reusable,
 * connection-parameterised ReshapeCustomFieldValues so it is testable in
 * isolation and on both drivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ReshapeCustomFieldValues)->run(config('database.default'));
    }

    /**
     * Irreversible by design: reversing would silently destroy typed values
     * (there is no lossless mapping back to the single-column polymorphic
     * form). Restore from a backup instead (docs/DECLARATIVE_FIELDS.md §5, P1-4).
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Migration reshape_custom_field_values_typed is irreversible — restore from a backup to roll back.'
        );
    }
};
