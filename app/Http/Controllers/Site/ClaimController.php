<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Actions\Claims\SubmitClaim;
use App\Enums\ListingStatus;
use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\ClaimRequest;
use App\Models\Listing;
use App\Models\ListingClaim;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "This is my business" — a member asks to take over an existing listing.
 * Submitting only opens a moderation item; ownership moves in ModerateClaim,
 * never here.
 */
class ClaimController extends Controller
{
    public function store(ClaimRequest $request, Listing $listing, SubmitClaim $submit): RedirectResponse
    {
        abort_unless(
            $listing->status === ListingStatus::Published && $listing->published_at !== null,
            Response::HTTP_NOT_FOUND
        );

        $user = $this->currentUser($request);

        if ((int) $listing->user_id === (int) $user->getKey()) {
            return back()->withErrors(['message' => 'Обявата вече е ваша.']);
        }

        $pending = ListingClaim::query()
            ->where('listing_id', $listing->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', ModerationStatus::Pending->value)
            ->exists();

        if ($pending) {
            return back()->withErrors(['message' => 'Вече имате заявка за тази обява, която очаква разглеждане.']);
        }

        $submit->execute($listing, $user, $request->validated());

        return back()->with('status', 'Заявката е изпратена за разглеждане.');
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $user;
    }
}
