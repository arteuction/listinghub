<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Actions\Listings\CreateListing;
use App\Actions\Listings\SyncListingCustomFields;
use App\Actions\Listings\TransitionListing;
use App\Actions\Listings\UpdateListing;
use App\Enums\ListingStatus;
use App\Enums\ListingTransition;
use App\Exceptions\CustomFieldConflict;
use App\Exceptions\InvalidListingTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\ListingRequest;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * A member's own listings.
 *
 * Every action that touches a specific listing runs through ListingPolicy, so
 * ownership is checked in one place rather than re-implemented per action. The
 * index is scoped by query instead of by policy — a member must not even see
 * that someone else's listing exists.
 */
class ListingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->currentUser($request);

        return view('member.listings.index', [
            'listings' => $user->listings()
                ->with(['category', 'settlement'])
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Listing::class);

        return view('member.listings.form', $this->formData($request, null));
    }

    public function store(ListingRequest $request, CreateListing $create, SyncListingCustomFields $sync): RedirectResponse
    {
        $this->authorize('create', Listing::class);

        $user = $this->currentUser($request);
        $data = $request->validated();

        try {
            $listing = DB::transaction(function () use ($user, $data, $request, $create, $sync): Listing {
                $listing = $create->execute($user, $data);
                $sync->handle($listing, $this->customFieldInput($request->input('custom_fields', [])));

                return $listing;
            });
        } catch (CustomFieldConflict $e) {
            return $this->conflictBack($e);
        }

        return redirect()->route('member.listings.edit', $listing)
            ->with('status', 'Обявата е създадена като чернова.');
    }

    public function edit(Request $request, Listing $listing): View
    {
        $this->authorize('update', $listing);

        return view('member.listings.form', $this->formData($request, $listing));
    }

    public function update(ListingRequest $request, Listing $listing, UpdateListing $update, SyncListingCustomFields $sync): RedirectResponse
    {
        $this->authorize('update', $listing);

        $data = $request->validated();

        try {
            DB::transaction(function () use ($listing, $data, $request, $update, $sync): void {
                $update->execute($listing, $data);
                $sync->handle($listing->refresh(), $this->customFieldInput($request->input('custom_fields', [])));
            });
        } catch (CustomFieldConflict $e) {
            return $this->conflictBack($e);
        }

        return back()->with('status', 'Промените са запазени.');
    }

    /**
     * Publishing goes through the closed transition map, never a direct status
     * write: with moderation on, a draft becomes Pending; with it off, it is
     * auto-published.
     */
    public function submit(Request $request, Listing $listing, TransitionListing $transition): RedirectResponse
    {
        $this->authorize('submit', $listing);

        $needsApproval = (bool) config('listinghub.moderation.listings_require_approval', true);

        try {
            $transition->handle(
                $listing,
                $needsApproval ? ListingTransition::Submit : ListingTransition::AutoPublish,
            );
        } catch (InvalidListingTransition) {
            // The domain message names internal statuses; members get a plain one.
            return back()->withErrors(['listing' => 'Обявата не може да бъде изпратена в текущото ѝ състояние.']);
        }

        return back()->with('status', $needsApproval
            ? 'Обявата е изпратена за одобрение.'
            : 'Обявата е публикувана.');
    }

    public function destroy(Listing $listing): RedirectResponse
    {
        $this->authorize('delete', $listing);

        // Soft delete: reviews, leads and payments reference this row.
        $listing->delete();

        return redirect()->route('member.listings.index')->with('status', 'Обявата е изтрита.');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, ?Listing $listing): array
    {
        $categoryId = $listing?->category_id ?? $request->query('category');
        $category = $categoryId !== null
            ? Category::query()->where('is_active', true)->find($categoryId)
            : null;

        return [
            'listing' => $listing,
            'category' => $category,
            'categories' => Category::query()->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')->get(),
            'regions' => Region::query()->orderBy('name')->get(),
            'fields' => $category !== null
                ? $category->customFields()->orderBy('sort_order')->orderBy('id')->get()
                : collect(),
            'values' => $listing !== null
                ? $listing->customFieldValues()->get()->keyBy('custom_field_id')
                : collect(),
            'statuses' => ListingStatus::class,
        ];
    }

    /** @return array<string, mixed> */
    private function customFieldInput(mixed $input): array
    {
        return is_array($input) ? $input : [];
    }

    private function conflictBack(CustomFieldConflict $e): RedirectResponse
    {
        $key = $e->fieldKey !== null ? "custom_fields.{$e->fieldKey}" : 'listing';

        return back()->withInput()->withErrors([$key => $e->getMessage()]);
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
