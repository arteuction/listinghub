<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Listings\CreateHourException;
use App\Actions\Listings\SyncListingHours;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListingHourExceptionRequest;
use App\Http\Requests\Admin\ListingHoursRequest;
use App\Models\Listing;
use App\Models\ListingHourException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ListingHoursController extends Controller
{
    public function edit(Listing $listing): View
    {
        return view('admin.hours.edit', [
            'listing' => $listing,
            'hours' => $listing->hours()->orderBy('day_of_week')->get()->keyBy('day_of_week'),
            'exceptions' => $listing->hourExceptions()->orderByDesc('date')->get(),
        ]);
    }

    public function update(ListingHoursRequest $request, Listing $listing, SyncListingHours $sync): RedirectResponse
    {
        $sync->handle($listing, $request->validated()['days']);

        return redirect()->route('admin.listings.hours.edit', $listing);
    }

    public function storeException(ListingHourExceptionRequest $request, Listing $listing, CreateHourException $create): RedirectResponse
    {
        $create->execute($listing, $request->validated());

        return redirect()->route('admin.listings.hours.edit', $listing);
    }

    public function destroyException(Listing $listing, ListingHourException $exception): RedirectResponse
    {
        abort_unless((int) $exception->listing_id === (int) $listing->getKey(), Response::HTTP_NOT_FOUND);

        $exception->delete();

        return redirect()->route('admin.listings.hours.edit', $listing);
    }
}
