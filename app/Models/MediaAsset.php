<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Single polymorphic gallery for listings, products and reviews.
 */
class MediaAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'mediable_id', 'mediable_type', 'collection', 'disk', 'path',
        'file_name', 'mime_type', 'size_bytes', 'alt_text', 'sort_order', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
