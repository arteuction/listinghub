<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Models\Listing;
use Illuminate\Support\Facades\DB;

/**
 * Bumps the view counter for a public listing detail page.
 *
 * Goes through the query builder rather than the model so the increment is
 * atomic under concurrency and leaves updated_at alone — a page view is not a
 * content change, and touching the timestamp would churn caches and sitemaps.
 */
final class RecordListingView
{
    public function execute(Listing $listing): void
    {
        DB::table('listings')
            ->where('id', $listing->getKey())
            ->increment('views');
    }
}
