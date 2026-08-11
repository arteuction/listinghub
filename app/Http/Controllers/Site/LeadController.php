<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Actions\Leads\CaptureLead;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\LeadRequest;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Contact this business" on a listing's public page. Anonymous by design —
 * the route throttle, not an account requirement, is what limits abuse.
 */
class LeadController extends Controller
{
    public function store(LeadRequest $request, Listing $listing, CaptureLead $capture): RedirectResponse
    {
        // An enquiry to an unpublished listing must be impossible, and must
        // not reveal that the row exists.
        abort_unless(
            $listing->status === ListingStatus::Published && $listing->published_at !== null,
            Response::HTTP_NOT_FOUND
        );

        $capture->execute($listing, $request->validated());

        return back()->with('status', 'Съобщението е изпратено.');
    }
}
