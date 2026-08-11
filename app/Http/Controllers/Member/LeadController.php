<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The owner's enquiry inbox for one listing. Reading is scoped to the parent
 * listing (ListingPolicy::update — if you can edit the listing, you can read
 * its leads); marking read additionally confirms the lead belongs to it.
 */
class LeadController extends Controller
{
    public function index(Listing $listing): View
    {
        $this->authorize('update', $listing);

        return view('member.leads.index', [
            'listing' => $listing,
            'leads' => $listing->leads()->orderByDesc('id')->paginate(20),
        ]);
    }

    public function markRead(Listing $listing, ListingLead $lead): RedirectResponse
    {
        $this->authorize('update', $listing);
        abort_unless((int) $lead->listing_id === (int) $listing->getKey(), Response::HTTP_NOT_FOUND);

        // Idempotent: re-marking an already-read lead must not move the
        // timestamp, or "when did this arrive" becomes unanswerable.
        if ($lead->read_at === null) {
            $lead->read_at = now();
            $lead->save();
        }

        return back()->with('status', 'Запитването е отбелязано като прочетено.');
    }
}
