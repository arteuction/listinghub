<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ListingStatus;
use App\Enums\ListingTransition;
use DomainException;

class InvalidListingTransition extends DomainException
{
    public static function for(ListingTransition $transition, ListingStatus $current): self
    {
        return new self(sprintf(
            'Cannot %s a listing in status "%s"; it must be "%s".',
            $transition->value,
            $current->value,
            $transition->fromStatus()->value,
        ));
    }
}
