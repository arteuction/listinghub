<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Claims\ModerateClaim;
use App\Actions\Reviews\ModerateReview;
use App\Enums\ListingStatus;
use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingClaim;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One queue for everything awaiting a decision: listings pending approval,
 * pending reviews, and pending ownership claims.
 *
 * Decisions are delegated to domain actions (TransitionListing is reached
 * through the existing admin listing flow; reviews and claims through
 * ModerateReview / ModerateClaim) so the aggregate rebuild and the
 * ownership transfer stay in one place rather than being re-implemented here.
 */
class ModerationController extends Controller
{
    public function index(): View
    {
        return view('admin.moderation.index', [
            'listings' => Listing::query()
                ->where('status', ListingStatus::Pending->value)
                ->with(['owner', 'category'])
                ->orderBy('id')
                ->limit(50)
                ->get(),
            'reviews' => Review::query()
                ->where('status', ModerationStatus::Pending->value)
                ->with(['listing', 'user'])
                ->orderBy('id')
                ->limit(50)
                ->get(),
            'claims' => ListingClaim::query()
                ->where('status', ModerationStatus::Pending->value)
                ->with(['listing', 'user'])
                ->orderBy('id')
                ->limit(50)
                ->get(),
        ]);
    }

    public function decideReview(Request $request, Review $review, ModerateReview $moderate): RedirectResponse
    {
        $moderate->handle($review, $this->decision($request));

        return redirect()->route('admin.moderation.index');
    }

    public function decideClaim(Request $request, ListingClaim $claim, ModerateClaim $moderate): RedirectResponse
    {
        $moderate->handle($claim, $this->decision($request));

        return redirect()->route('admin.moderation.index');
    }

    /**
     * The form posts `approve` or `reject`; anything else is rejected outright
     * rather than defaulted, so a malformed request cannot silently approve.
     */
    private function decision(Request $request): ModerationStatus
    {
        return $request->input('decision') === 'approve'
            ? ModerationStatus::Approved
            : ModerationStatus::Rejected;
    }
}
