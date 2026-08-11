<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Enums\ListingTransition;

dataset('legal_transitions', [
    'submit' => [ListingTransition::Submit, ListingStatus::Draft, ListingStatus::Pending],
    'auto_publish' => [ListingTransition::AutoPublish, ListingStatus::Draft, ListingStatus::Published],
    'approve' => [ListingTransition::Approve, ListingStatus::Pending, ListingStatus::Published],
    'disapprove' => [ListingTransition::Disapprove, ListingStatus::Published, ListingStatus::Pending],
    'suspend' => [ListingTransition::Suspend, ListingStatus::Published, ListingStatus::Suspended],
    'restore' => [ListingTransition::Restore, ListingStatus::Suspended, ListingStatus::Published],
]);

it('maps each transition to the correct from/to', function (ListingTransition $t, ListingStatus $from, ListingStatus $to) {
    expect($t->fromStatus())->toBe($from)
        ->and($t->toStatus())->toBe($to);
})->with('legal_transitions');

it('exposes exactly six transitions and no Rejected/Archived', function () {
    expect(ListingTransition::cases())->toHaveCount(6);

    $statuses = array_map(fn ($c) => $c->value, ListingStatus::cases());
    expect($statuses)->not->toContain('rejected')
        ->and($statuses)->not->toContain('archived');
});

it('resolves the transition between two statuses, and null for illegal pairs', function () {
    expect(ListingTransition::between(ListingStatus::Pending, ListingStatus::Published))
        ->toBe(ListingTransition::Approve);

    // Draft -> Suspended is not a legal move.
    expect(ListingTransition::between(ListingStatus::Draft, ListingStatus::Suspended))
        ->toBeNull();
});
