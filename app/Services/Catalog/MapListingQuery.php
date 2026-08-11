<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Listing;
use App\Support\BBox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Extends PublicListingQuery for the map endpoint:
 *
 *  1. Strips eager loads (map only needs scalar fields, not relations).
 *  2. LEFT JOINs settlements so we can fall back to the settlement's
 *     centroid when the listing itself has no explicit coordinates.
 *  3. Filters by the bbox and excludes listings with no resolvable coords.
 *  4. Returns only the scalar columns the GeoJSON serialiser needs.
 *
 * FILTER PARITY — the public filter (published status, keyword, category,
 * region/municipality/settlement) is applied by PublicListingQuery::build(),
 * which guarantees map and list show the same set of listings.
 */
final class MapListingQuery
{
    public const MAX_FEATURES = 1000;

    public function __construct(private readonly PublicListingQuery $catalog) {}

    /**
     * @param  array<string, mixed>  $params  Same keys as PublicListingQuery (no sort needed).
     * @return Builder<Listing>
     */
    public function build(array $params, BBox $bbox): Builder
    {
        $base = $this->catalog->build(array_merge($params, ['sort' => null]))
            ->withoutEagerLoads();

        // LEFT JOIN settlements for coordinate fallback.
        // Alias "map_s" avoids colliding with any join added by PublicListingQuery.
        $base->leftJoin('settlements as map_s', 'map_s.id', '=', 'listings.settlement_id');

        // Effective coordinates: explicit listing point > settlement centroid.
        $effLat = 'COALESCE(listings.latitude, map_s.latitude)';
        $effLng = 'COALESCE(listings.longitude, map_s.longitude)';

        $base->select([
            'listings.id',
            'listings.title',
            'listings.slug',
            'listings.latitude',
            'listings.longitude',
            DB::raw("{$effLat} as eff_lat"),
            DB::raw("{$effLng} as eff_lng"),
            DB::raw('CASE WHEN listings.latitude IS NOT NULL THEN 1 ELSE 0 END as has_exact_coords'),
            'map_s.name as settlement_name',
        ]);

        // Exclude listings with no resolvable coordinates.
        $base->whereRaw("{$effLat} IS NOT NULL AND {$effLng} IS NOT NULL");

        // Bbox filter. Bound floats are CAST to DECIMAL explicitly: some PDO
        // SQLite builds bind PHP floats as TEXT rather than REAL, and SQLite
        // orders TEXT after every INTEGER/REAL value — an uncast BETWEEN
        // would then silently compare false for every row. CAST forces
        // numeric affinity on both sides and is valid on SQLite and MySQL.
        $base->whereRaw("CAST({$effLat} AS DECIMAL(10,7)) BETWEEN CAST(? AS DECIMAL(10,7)) AND CAST(? AS DECIMAL(10,7))", [$bbox->south, $bbox->north]);
        $base->whereRaw("CAST({$effLng} AS DECIMAL(10,7)) BETWEEN CAST(? AS DECIMAL(10,7)) AND CAST(? AS DECIMAL(10,7))", [$bbox->west, $bbox->east]);

        // Cap rows to prevent oversized responses; clustering is client-side.
        $base->limit(self::MAX_FEATURES);

        return $base;
    }
}
