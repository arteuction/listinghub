<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;

/**
 * Read/write access to grouped key/value settings. Query logic lives here,
 * not on the Eloquent model.
 */
class SettingsRepository
{
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return Setting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->value('value') ?? $default;
    }

    public function set(string $group, string $key, ?string $value): Setting
    {
        return Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value],
        );
    }
}
