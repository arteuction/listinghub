<?php

declare(strict_types=1);

namespace App\Actions\Publishing;

use App\Models\ScheduledPublication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Schedules a future publish or unpublish for a content resource.
 *
 * At most one pending action of the same type may exist per resource at a time;
 * scheduling again replaces the previous pending entry atomically.
 */
final class SchedulePublication
{
    public const ACTION_PUBLISH = 'publish';

    public const ACTION_UNPUBLISH = 'unpublish';

    public function handle(
        Model $resource,
        string $action,
        CarbonImmutable $scheduledFor,
        ?User $actor = null,
    ): ScheduledPublication {
        if (! in_array($action, [self::ACTION_PUBLISH, self::ACTION_UNPUBLISH], true)) {
            throw new InvalidArgumentException("Unknown publication action \"{$action}\".");
        }

        if ($scheduledFor->isPast()) {
            throw new InvalidArgumentException('Scheduled time must be in the future.');
        }

        return DB::transaction(function () use ($resource, $action, $scheduledFor, $actor): ScheduledPublication {
            // Cancel any pending action of the same type on this resource.
            ScheduledPublication::query()
                ->where('schedulable_type', $resource->getMorphClass())
                ->where('schedulable_id', $resource->getKey())
                ->where('action', $action)
                ->whereNull('processed_at')
                ->delete();

            return ScheduledPublication::query()->create([
                'schedulable_type' => $resource->getMorphClass(),
                'schedulable_id' => $resource->getKey(),
                'action' => $action,
                'scheduled_for' => $scheduledFor,
                'created_by' => $actor?->getKey(),
            ]);
        });
    }
}
