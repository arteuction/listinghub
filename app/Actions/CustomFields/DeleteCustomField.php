<?php

declare(strict_types=1);

namespace App\Actions\CustomFields;

use App\Exceptions\CustomFieldConflict;
use App\Models\CustomField;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a custom field definition ONLY when it has no stored values.
 * Archiving a field that is in use is a separate, future schema step — deleting
 * it here would destroy listing data via the FK cascade.
 */
class DeleteCustomField
{
    public function handle(CustomField $field): void
    {
        DB::transaction(function () use ($field): void {
            /** @var CustomField $locked */
            $locked = CustomField::query()->lockForUpdate()->findOrFail($field->getKey());

            if ($locked->values()->exists()) {
                throw CustomFieldConflict::make('Cannot delete a field that has values; archive it instead.');
            }

            $locked->delete();
        });
    }
}
