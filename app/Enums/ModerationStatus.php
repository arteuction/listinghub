<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Shared approval lifecycle for moderated records (reviews, claims).
 */
enum ModerationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
