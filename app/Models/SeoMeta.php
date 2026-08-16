<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'meta_title',
        'meta_description',
        'robots',
        'canonical_path',
        'og',
        'structured_data',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'og' => 'array',
            'structured_data' => 'array',
        ];
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
