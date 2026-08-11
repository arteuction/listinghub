<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The complete, closed set of legal listing status transitions.
 *
 * There is deliberately no Rejected or Archived operation — the original
 * product uses only Submitted (→ Pending here), Published and Suspended.
 * This enum IS the transition map; nothing else defines allowed moves.
 *
 * NOTE: methods are named fromStatus()/toStatus() — a backed enum already
 * defines the reserved static from()/tryFrom(); redefining from() is fatal.
 */
enum ListingTransition: string
{
    case Submit = 'submit';
    case AutoPublish = 'auto_publish';
    case Approve = 'approve';
    case Disapprove = 'disapprove';
    case Suspend = 'suspend';
    case Restore = 'restore';

    public function fromStatus(): ListingStatus
    {
        return match ($this) {
            self::Submit, self::AutoPublish => ListingStatus::Draft,
            self::Approve => ListingStatus::Pending,
            self::Disapprove, self::Suspend => ListingStatus::Published,
            self::Restore => ListingStatus::Suspended,
        };
    }

    public function toStatus(): ListingStatus
    {
        return match ($this) {
            self::Submit => ListingStatus::Pending,
            self::AutoPublish, self::Approve, self::Restore => ListingStatus::Published,
            self::Disapprove => ListingStatus::Pending,
            self::Suspend => ListingStatus::Suspended,
        };
    }

    /**
     * The moves legal from $from.
     *
     * The admin UI renders its buttons from this, so what an operator can
     * click is derived from the map rather than restated beside it — a
     * transition removed here disappears from the interface with it.
     *
     * Submit and AutoPublish are excluded: both leave Draft, and which one
     * applies is decided by the moderation setting when the OWNER submits.
     * An admin publishing a draft directly would bypass that decision.
     *
     * @return list<self>
     */
    public static function availableFrom(ListingStatus $from): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $transition): bool => $transition->fromStatus() === $from
                && ! in_array($transition, [self::Submit, self::AutoPublish], true),
        ));
    }

    /** Imperative label for the admin button that applies this move. */
    public function adminLabel(): string
    {
        return match ($this) {
            self::Submit => 'Изпрати за одобрение',
            self::AutoPublish => 'Публикувай',
            self::Approve => 'Одобри',
            self::Disapprove => 'Върни за преглед',
            self::Suspend => 'Спри',
            self::Restore => 'Възстанови',
        };
    }

    /**
     * Resolve the transition connecting two statuses, or null if the move is
     * not part of the legal map.
     */
    public static function between(ListingStatus $from, ListingStatus $to): ?self
    {
        foreach (self::cases() as $transition) {
            if ($transition->fromStatus() === $from && $transition->toStatus() === $to) {
                return $transition;
            }
        }

        return null;
    }
}
