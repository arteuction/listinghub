<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/catalog/settlements?q=Sofia
 *
 * EKATTE autocomplete: free-text search over settlement names, returning
 * the top 10 matches with region/municipality context. Always scoped to
 * settlements that have coordinates so they can appear on the map.
 */
class SettlementSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([], 200)
                ->header('Cache-Control', 'public, max-age=0');
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);

        $rows = Settlement::query()
            ->with('municipality.region')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$escaped.'%'])
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'slug', 'type', 'municipality_id']);

        $results = $rows->map(fn (Settlement $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'slug' => $s->slug,
            'type' => $s->type,
            'municipality' => $s->municipality?->name,
            'region' => $s->municipality?->region?->name,
            'region_slug' => $s->municipality?->region?->slug,
        ]);

        return response()->json($results)
            ->header('Cache-Control', 'public, max-age=300');
    }
}
