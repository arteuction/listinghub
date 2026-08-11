<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Actions\Listings\RecordListingView;
use App\Enums\ListingStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\ListingIndexRequest;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\Settlement;
use App\Services\Catalog\PublicListingQuery;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ListingController extends Controller
{
    public function __construct(private readonly PublicListingQuery $catalog) {}

    /**
     * Serves three URL shapes with one action:
     *   /listings?category=…&region=…   — the search/filter entry point
     *   /categories/{category}          — canonical category page
     *   /regions/{region}               — canonical region page
     *
     * A bound route parameter always wins over the equivalent query string, so
     * a canonical page cannot be re-pointed at another category via ?category=.
     */
    public function index(
        ListingIndexRequest $request,
        ?Category $category = null,
        ?Region $region = null,
    ): View {
        if ($category !== null && ! $category->is_active) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $category ??= $this->resolveCategory($request->query('category'));
        $region ??= $this->resolveRegion($request->query('region'));
        $municipality = $this->resolveMunicipality($request->query('municipality'), $region);
        $settlement = $this->resolveSettlement($request->query('settlement'), $municipality);

        $listings = $this->catalog->build([
            'q' => $request->keyword(),
            'sort' => $request->sortKey(),
            'category' => $category,
            'region' => $region,
            'municipality' => $municipality,
            'settlement' => $settlement,
        ])->paginate(PublicListingQuery::PER_PAGE)->withQueryString();

        return view('site.listings.index', [
            'listings' => $listings,
            'keyword' => $request->keyword(),
            'sortKey' => $request->sortKey() ?? PublicListingQuery::DEFAULT_SORT,
            'sorts' => PublicListingQuery::SORTS,
            'category' => $category,
            'region' => $region,
            'municipality' => $municipality,
            'settlement' => $settlement,
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'regions' => Region::query()->orderBy('name')->get(),
        ]);
    }

    public function show(Listing $listing, RecordListingView $recordView): View
    {
        // A draft, pending or suspended listing must be indistinguishable from
        // one that does not exist.
        abort_unless(
            $listing->status === ListingStatus::Published && $listing->published_at !== null,
            Response::HTTP_NOT_FOUND
        );

        $listing->load([
            'category',
            'settlement.municipality.region',
            'media',
            // A draft or suspended product must not surface on a public page.
            'products' => fn ($query) => $query
                ->where('status', ProductStatus::Published->value)
                ->orderBy('sort_order')
                ->orderBy('name'),
            'hours' => fn ($query) => $query->orderBy('day_of_week'),
        ]);

        $recordView->execute($listing);

        return view('site.listings.show', [
            'listing' => $listing,
            'related' => $this->catalog->build(['category' => $listing->category])
                ->whereKeyNot($listing->getKey())
                ->limit(4)
                ->get(),
        ]);
    }

    private function resolveCategory(mixed $slug): ?Category
    {
        return is_string($slug) && $slug !== ''
            ? Category::query()->where('slug', $slug)->where('is_active', true)->first()
            : null;
    }

    private function resolveRegion(mixed $slug): ?Region
    {
        return is_string($slug) && $slug !== ''
            ? Region::query()->where('slug', $slug)->first()
            : null;
    }

    /** A municipality is only honoured when it belongs to the selected region. */
    private function resolveMunicipality(mixed $slug, ?Region $region): ?Municipality
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $query = Municipality::query()->where('slug', $slug);

        if ($region !== null) {
            $query->where('region_id', $region->getKey());
        }

        return $query->first();
    }

    /** Likewise a settlement must belong to the selected municipality. */
    private function resolveSettlement(mixed $slug, ?Municipality $municipality): ?Settlement
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $query = Settlement::query()->where('slug', $slug);

        if ($municipality !== null) {
            $query->where('municipality_id', $municipality->getKey());
        }

        return $query->first();
    }
}
