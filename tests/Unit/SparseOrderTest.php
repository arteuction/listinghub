<?php

declare(strict_types=1);

use App\Support\SparseOrder;

it('starts at the base step', function () {
    expect(SparseOrder::first())->toBe(1000);
});

it('appends one step past the current max', function () {
    expect(SparseOrder::append(3000))->toBe(4000);
});

it('returns the midpoint when a gap exists', function () {
    expect(SparseOrder::between(1000, 2000))->toBe(1500);
});

it('returns null when no integer gap remains', function () {
    expect(SparseOrder::between(1000, 1001))->toBeNull();
});

it('rebalances into evenly spaced positions', function () {
    expect(SparseOrder::rebalance(3))->toBe([1000, 2000, 3000]);
});
