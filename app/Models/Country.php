<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Single-record model. ListingHub is a Bulgaria-only platform (iso2=BG).
 * No country selector is exposed in the public UI.
 */
class Country extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'iso2', 'slug'];

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }
}
