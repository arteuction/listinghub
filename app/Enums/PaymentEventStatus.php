<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentEventStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case Failed = 'failed';
    case Ignored = 'ignored';
}
