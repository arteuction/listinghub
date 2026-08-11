<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MapRequest;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\Settlement;
use App\Services\Catalog\MapListingQuery;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/catalog/map
 *
 * Returns a GeoJSON FeatureCollection of published listings within the
 * requested bbox. Applies the exact same public filters as the catalog page
 * (category, region, municipality, settlement, keyword) so map and list
 * always show the same set.
 *
 * Coordinate resolution: listing.latitude/longitude when explicitly set;
 * falls back to the settlement centroid otherwise.
 */
class MapController extends Controller
{
    public function __construct(private readonly MapListingQuery $map) {}

    public function __invoke(MapRequest $request): JsonResponse
    {
        $bbox = $request->bbox();

        if ($bbox === null) {
            return response()->json([
                'message' => 'bbox must be "west,south,east,north" within Bulgaria.',
            ], 422);
        }

        $category = $this->resolveCategory($request->query('category'));
        $region = $this->resolveRegion($request->query('region'));
        $municipality = $this->resolveMunicipality($request->query('municipality'), $region);
        $settlement = $this->resolveSettlement($request->query('settlement'), $municipality);

        $rows = $this->map->build([
            'q' => $request->keyword(),
            'category' => $category,
            'region' => $region,
            'municipality' => $municipality,
            'settlement' => $settlement,
        ], $bbox)->get();

        // eff_lat/eff_lng/settlement_name/has_exact_coords are query-only
        // aliases from MapListingQuery's select() (not real Listing columns),
        // so they're read via array access — Model implements ArrayAccess and
        // returns mixed, which keeps static analysis honest without adding
        // fictitious @property entries to the real Listing schema.
        $features = $rows->map(function (Listing $row): array {
            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $row['eff_lng'], (float) $row['eff_lat']],
                ],
                'properties' => [
                    'id' => $row->id,
                    'title' => $row->title,
                    'url' => route('listings.show', $row->slug),
                    'settlement' => $row['settlement_name'],
                    'exact' => (bool) $row['has_exact_coords'],
                ],
            ];
        });

        $response = response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);

        // Public, short-lived: allows CDN/browser cache but must revalidate.
        $response->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=30');

        return $response;
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
