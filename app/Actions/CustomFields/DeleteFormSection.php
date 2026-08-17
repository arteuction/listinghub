<?php

declare(strict_types=1);

namespace App\Actions\CustomFields;

use App\Models\FormSection;
use InvalidArgumentException;

final class DeleteFormSection
{
    public function handle(FormSection $section): void
    {
        if ($section->fields()->exists()) {
            throw new InvalidArgumentException(
                "Cannot delete section \"{$section->title}\": it still has assigned fields. Move or delete those fields first."
            );
        }

        $section->delete();
    }
}
