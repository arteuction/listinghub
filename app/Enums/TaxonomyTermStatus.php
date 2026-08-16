<?php

declare(strict_types=1);

namespace App\Enums;

enum TaxonomyTermStatus: string
{
    case Published = 'published';
    case Draft     = 'draft';
    case Archived  = 'archived';
    case Hidden    = 'hidden'; // visible to admin, excluded from public listings
}
