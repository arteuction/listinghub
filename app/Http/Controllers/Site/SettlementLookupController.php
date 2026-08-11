<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\Settlement;
use Illuminate\Http\JsonResponse;

/**
 * Settlements of one region, for the cascading location picker.
 *
 * Public, read-only geo reference data — the same rows the catalog already
 * exposes as filters. Scoped per region so the payload stays small; the full
 * table is 5,256 rows and must never be shipped to a form in one go.
 */
class SettlementLookupController extends Controller
{
    public function __invoke(Region $region): JsonResponse
    {
        $settlements = Settlement::query()
            ->whereIn(
                'municipality_id',
                $region->municipalities()->select('id')
            )
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'municipality_id'])
            ->map(fn (Settlement $settlement): array => [
                'id' => $settlement->getKey(),
                'name' => $settlement->name,
                'type' => $settlement->type,
            ])
            ->values();

        return response()->json($settlements)
            // Reference data changes at dataset-update cadence, not per request.
            ->setPublic()
            ->setMaxAge(3600);
    }
}
