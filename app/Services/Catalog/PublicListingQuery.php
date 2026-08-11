<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\Settlement;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the public catalog query: published listings only, narrowed by
 * category, Bulgarian location, and keyword, then ordered.
 *
 * Separate from App\Services\Search\ListingSearchQuery, which answers typed
 * custom-field queries within a single category. This one is the broad
 * browse/search path and never exposes a non-published listing.
 */
final class PublicListingQuery
{
    public const PER_PAGE = 24;

    /**
     * Sort key => human label. The key is what appears in the query string;
     * anything else falls back to the default.
     */
    public const SORTS = [
        'newest' => 'Най-нови',
        'oldest' => 'Най-стари',
        'rating' => 'Най-високо оценени',
        'popular' => 'Най-разглеждани',
        'title' => 'По азбучен ред',
    ];

    public const DEFAULT_SORT = 'newest';

    /**
     * @param  array<string, mixed>  $params  q, category, region, municipality, settlement, sort, featured
     */
    /** @return Builder<Listing> */
    public function build(array $params): Builder
    {
        $query = Listing::query()
            ->where('listings.status', ListingStatus::Published->value)
            ->whereNotNull('listings.published_at')
            ->with(['category', 'settlement.municipality.region', 'media']);

        $this->applyKeyword($query, $params['q'] ?? null);
        $this->applyCategory($query, $params['category'] ?? null);
        $this->applyLocation($query, $params);

        if (($params['featured'] ?? null) === true) {
            $query->where('listings.is_featured', true);
        }

        return $this->applySort($query, $params['sort'] ?? null);
    }

    /**
     * Ids of a category and every category beneath it, so browsing a parent
     * shows the whole subtree. The category table is small enough to resolve
     * the tree in one read rather than a recursive CTE.
     *
     * @return list<int>
     */
    public function categorySubtreeIds(Category $category): array
    {
        /** @var array<int, list<int>> $childrenByParent */
        $childrenByParent = [];

        foreach (Category::query()->get(['id', 'parent_id']) as $row) {
            $childrenByParent[(int) $row->parent_id][] = (int) $row->id;
        }

        $ids = [];
        $stack = [(int) $category->getKey()];

        while ($stack !== []) {
            $id = array_pop($stack);

            if (in_array($id, $ids, true)) {
                continue; // defensive: a cycle must not hang the request
            }

            $ids[] = $id;

            foreach ($childrenByParent[$id] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    private function applyKeyword(Builder $query, mixed $q): void
    {
        $term = is_string($q) ? trim($q) : '';

        if ($term === '') {
            return;
        }

        // Escape the LIKE wildcards so a user-supplied % or _ is literal.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
        $like = '%'.$escaped.'%';

        $query->where(function (Builder $inner) use ($like): void {
            $inner->where('listings.title', 'like', $like)
                ->orWhere('listings.description', 'like', $like);
        });
    }

    private function applyCategory(Builder $query, mixed $category): void
    {
        if (! $category instanceof Category) {
            return;
        }

        $query->whereIn('listings.category_id', $this->categorySubtreeIds($category));
    }

    /**
     * Location narrows through the Bulgarian hierarchy. The most specific
     * value wins: settlement, else municipality, else region.
     *
     * @param  array<string, mixed>  $params
     */
    private function applyLocation(Builder $query, array $params): void
    {
        $settlement = $params['settlement'] ?? null;
        $municipality = $params['municipality'] ?? null;
        $region = $params['region'] ?? null;

        if ($settlement instanceof Settlement) {
            $query->where('listings.settlement_id', $settlement->getKey());

            return;
        }

        if ($municipality instanceof Municipality) {
            $query->whereIn(
                'listings.settlement_id',
                Settlement::query()
                    ->where('municipality_id', $municipality->getKey())
                    ->select('id')
            );

            return;
        }

        if ($region instanceof Region) {
            $query->whereIn(
                'listings.settlement_id',
                Settlement::query()
                    ->whereIn(
                        'municipality_id',
                        Municipality::query()->where('region_id', $region->getKey())->select('id')
                    )
                    ->select('id')
            );
        }
    }

    /**
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    private function applySort(Builder $query, mixed $sort): Builder
    {
        $key = is_string($sort) && array_key_exists($sort, self::SORTS) ? $sort : self::DEFAULT_SORT;

        // Featured listings lead every ordering — that is what the plan buys.
        $query->orderByDesc('listings.is_featured');

        match ($key) {
            'oldest' => $query->orderBy('listings.published_at'),
            'rating' => $query->orderByDesc('listings.rating_avg')->orderByDesc('listings.rating_count'),
            'popular' => $query->orderByDesc('listings.views'),
            'title' => $query->orderBy('listings.title'),
            default => $query->orderByDesc('listings.published_at'),
        };

        // Deterministic tie-break so pagination cannot repeat or skip a row.
        return $query->orderByDesc('listings.id');
    }
}
