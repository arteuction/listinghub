<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sparse integer ordering. Positions are spaced by STEP so that inserting
 * between two neighbours only needs a midpoint write — never a cascade of
 * rank-up/rank-down swaps. A full rebalance happens only when a gap runs out.
 *
 * Pure and DB-agnostic; the persistence/locking lives in the reposition
 * actions that use it.
 */
final class SparseOrder
{
    public const STEP = 1000;

    /** Position for the first item in an empty scope. */
    public static function first(): int
    {
        return self::STEP;
    }

    /** Position appended after the current maximum. */
    public static function append(int $currentMax): int
    {
        return $currentMax + self::STEP;
    }

    /**
     * Midpoint between two positions, or null when no integer gap remains
     * (the caller must then rebalance the scope).
     */
    public static function between(int $lower, int $upper): ?int
    {
        $mid = intdiv($lower + $upper, 2);

        return ($mid > $lower && $mid < $upper) ? $mid : null;
    }

    /**
     * Evenly spaced positions for a scope of $count items: 1000, 2000, ...
     *
     * @return list<int>
     */
    public static function rebalance(int $count): array
    {
        $positions = [];
        for ($i = 1; $i <= $count; $i++) {
            $positions[] = $i * self::STEP;
        }

        return $positions;
    }
}
