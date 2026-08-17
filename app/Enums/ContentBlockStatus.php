<?php

declare(strict_types=1);

namespace App\Enums;

enum ContentBlockStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
