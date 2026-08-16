<?php

declare(strict_types=1);

namespace App\Enums;

enum ContentBlockRevisionOperation: string
{
    case Created = 'created';
    case Updated = 'updated';
    case RolledBack = 'rolled_back';
}
