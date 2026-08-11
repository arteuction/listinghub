<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Actions\Listings\CreateHourException;
use App\Actions\Listings\SyncListingHours;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\ListingHourExceptionRequest;
use App\Http\Requests\Member\ListingHoursRequest;
use App\Models\Listing;
use App\Models\ListingHourException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Weekly schedule + date-specific exceptions for a listing the member owns.
 * Both are part of managing the listing, so every action authorises against
 * the listing itself (ListingPolicy::update), not a separate policy.
 */
class ListingHoursController extends Controller
{
    public function edit(Listing $listing): View
    {
        $this->authorize('update', $listing);

        return view('member.hours.edit', [
            'listing' => $listing,
            'hours' => $listing->hours()->orderBy('day_of_week')->get()->keyBy('day_of_week'),
            'exceptions' => $listing->hourExceptions()->orderByDesc('date')->get(),
        ]);
    }

    public function update(ListingHoursRequest $request, Listing $listing, SyncListingHours $sync): RedirectResponse
    {
        $this->authorize('update', $listing);

        $sync->handle($listing, $request->validated()['days']);

        return redirect()->route('member.listings.hours.edit', $listing)
            ->with('status', 'Работното време е запазено.');
    }

    public function storeException(ListingHourExceptionRequest $request, Listing $listing, CreateHourException $create): RedirectResponse
    {
        $this->authorize('update', $listing);

        $create->execute($listing, $request->validated());

        return redirect()->route('member.listings.hours.edit', $listing)
            ->with('status', 'Изключението е добавено.');
    }

    public function destroyException(Listing $listing, ListingHourException $exception): RedirectResponse
    {
        $this->authorize('update', $listing);
        abort_unless((int) $exception->listing_id === (int) $listing->getKey(), Response::HTTP_NOT_FOUND);

        $exception->delete();

        return redirect()->route('member.listings.hours.edit', $listing)
            ->with('status', 'Изключението е премахнато.');
    }
}
