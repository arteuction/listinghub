<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Region;
use Illuminate\Http\Response;

/**
 * XML sitemap for the Bulgarian catalog.
 *
 * Emits only canonical, indexable URLs — home, active categories, regions and
 * published listings. Query-string browse permutations are intentionally
 * excluded: they are the same content under a different filter.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
        ];

        foreach (Category::query()->where('is_active', true)->orderBy('name')->get() as $category) {
            $urls[] = [
                'loc' => route('categories.show', $category->slug),
                'priority' => '0.8',
                'changefreq' => 'weekly',
            ];
        }

        foreach (Region::query()->orderBy('name')->get() as $region) {
            $urls[] = [
                'loc' => route('regions.show', $region->slug),
                'priority' => '0.7',
                'changefreq' => 'weekly',
            ];
        }

        $listings = Listing::query()
            ->where('status', ListingStatus::Published->value)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->get(['slug', 'updated_at']);

        foreach ($listings as $listing) {
            $urls[] = [
                'loc' => route('listings.show', $listing->slug),
                'lastmod' => $listing->updated_at?->toAtomString(),
                'priority' => '0.6',
                'changefreq' => 'weekly',
            ];
        }

        return response()
            ->view('site.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
