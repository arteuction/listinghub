<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Actions\Reviews\SubmitReview;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\ReviewRequest;
use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public review submission on a listing's detail page.
 *
 * Three rules that are enforced here rather than left to the schema:
 *  - only a PUBLISHED listing can be reviewed (an unpublished one 404s, same
 *    as its detail page, so a draft stays indistinguishable from nonexistent);
 *  - an owner cannot review their own listing;
 *  - one review per user per listing (also a UNIQUE key — checked here so the
 *    user gets a validation message instead of a constraint violation).
 */
class ReviewController extends Controller
{
    public function store(ReviewRequest $request, Listing $listing, SubmitReview $submit): RedirectResponse
    {
        abort_unless(
            $listing->status === ListingStatus::Published && $listing->published_at !== null,
            Response::HTTP_NOT_FOUND
        );

        $this->authorize('create', Review::class);

        $user = $this->currentUser($request);

        if ((int) $listing->user_id === (int) $user->getKey()) {
            return back()->withErrors(['rating' => 'Не можете да оцените собствената си обява.']);
        }

        $alreadyReviewed = Review::query()
            ->where('listing_id', $listing->getKey())
            ->where('user_id', $user->getKey())
            ->exists();

        if ($alreadyReviewed) {
            return back()->withErrors(['rating' => 'Вече сте оценили тази обява.']);
        }

        $submit->execute($listing, $user, $request->validated());

        return back()->with('status', config('listinghub.moderation.reviews_require_approval', true)
            ? 'Благодарим! Отзивът ви е изпратен за одобрение.'
            : 'Благодарим за отзива!');
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
