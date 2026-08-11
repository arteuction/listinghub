<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingInterval: string
{
    case Month = 'month';
    case Year = 'year';
    case Lifetime = 'lifetime';
}
