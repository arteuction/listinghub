<?php

declare(strict_types=1);

namespace App\Actions\CustomFields;

use App\Models\FormSection;
use InvalidArgumentException;

final class UpdateFormSection
{
    private const ALLOWED = ['title', 'description', 'sort_order', 'is_collapsible'];

    public function handle(FormSection $section, array $fields): FormSection
    {
        $unknown = array_diff(array_keys($fields), self::ALLOWED);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown fields: '.implode(', ', $unknown));
        }

        if (isset($fields['title'])) {
            $fields['title'] = trim($fields['title']);
            if ($fields['title'] === '') {
                throw new InvalidArgumentException('Title cannot be empty.');
            }
        }

        if (isset($fields['description'])) {
            $fields['description'] = trim($fields['description']) ?: null;
        }

        if (isset($fields['sort_order'])) {
            $fields['sort_order'] = max(0, (int) $fields['sort_order']);
        }

        $section->fill($fields)->save();

        return $section->fresh();
    }
}
