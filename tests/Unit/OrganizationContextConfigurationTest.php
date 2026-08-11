<?php

declare(strict_types=1);

it('ships with multitenancy disabled by default', function () {
    expect(config('listinghub.multitenancy.enabled'))->toBeFalse();
});

it('exposes the four listing statuses', function () {
    expect(config('listinghub.listing_statuses'))
        ->toContain('draft', 'pending', 'published', 'suspended');
});
