<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Plan;

/**
 * Builds the immutable plan snapshot stored on a subscription at purchase
 * time. Kept out of the Eloquent model so the model stays a data mapper.
 */
final class PlanSnapshot
{
    /** @return array<string, mixed> */
    public static function fromPlan(Plan $plan): array
    {
        return [
            'plan_slug_snapshot' => $plan->slug,
            'plan_name_snapshot' => $plan->name,
            'price_minor_snapshot' => $plan->price_minor,
            'currency_snapshot' => $plan->currency,
            'interval_snapshot' => $plan->interval->value,
        ];
    }
}
