<?php

declare(strict_types=1);

namespace App\Enums;

enum ContentBlockRevisionOperation: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Published = 'published';
    case Unpublished = 'unpublished';
    case RolledBack = 'rolled_back';
    case Deleted = 'deleted';
}
