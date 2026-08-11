<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saved listings.
 *
 * Only published listings can be saved — otherwise a member could keep a handle
 * on a draft or suspended listing and watch it through the favourites list.
 * Attach/detach are idempotent, backed by the unique (user, listing) pair.
 */
class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->currentUser($request);

        return view('member.favorites', [
            'listings' => $user->favorites()
                ->where('listings.status', ListingStatus::Published->value)
                ->with(['category', 'settlement.municipality.region', 'media'])
                ->paginate(20),
        ]);
    }

    public function store(Request $request, Listing $listing): RedirectResponse
    {
        abort_unless($listing->status === ListingStatus::Published, Response::HTTP_NOT_FOUND);

        // syncWithoutDetaching is a no-op when the row already exists, so a
        // double submit cannot fail on the unique index.
        $this->currentUser($request)->favorites()->syncWithoutDetaching([$listing->getKey()]);

        return back()->with('status', 'Добавено в любими.');
    }

    public function destroy(Request $request, Listing $listing): RedirectResponse
    {
        $this->currentUser($request)->favorites()->detach($listing->getKey());

        return back()->with('status', 'Премахнато от любими.');
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
