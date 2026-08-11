<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Grouped key/value setting. Read/write logic lives in
 * App\Services\Settings\SettingsRepository, not here.
 */
class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['group', 'key', 'value'];
}
