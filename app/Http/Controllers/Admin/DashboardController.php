<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ListingStatus;
use App\Enums\ModerationStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingClaim;
use App\Models\ListingLead;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\View\View;

/**
 * Panel landing page: what needs a decision, and the size of each collection.
 *
 * The counts are grouped in ONE query per table rather than one per status —
 * four statuses would otherwise mean four round trips for a number nobody
 * reads individually. Each block links to the screen that acts on it, so the
 * dashboard is a route into the work rather than a wall of figures.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'listings' => $this->countByStatus(Listing::query()->toBase(), 'status'),
            'users' => $this->countByStatus(User::query()->toBase(), 'status'),
            'reviews' => $this->countByStatus(Review::query()->toBase(), 'status'),
            'claims' => $this->countByStatus(ListingClaim::query()->toBase(), 'status'),
            'listingStatuses' => ListingStatus::cases(),
            'userStatuses' => UserStatus::cases(),
            'moderationStatuses' => ModerationStatus::cases(),
            'unreadLeads' => ListingLead::query()->whereNull('read_at')->count(),
            'totalLeads' => ListingLead::query()->count(),
        ]);
    }

    /** @return array<string, int> status value => row count */
    private function countByStatus(Builder $query, string $column): array
    {
        /** @var array<string, int> $counts */
        $counts = $query->selectRaw($column.' as status_value, COUNT(*) as total')
            ->groupBy($column)
            ->pluck('total', 'status_value')
            ->map(fn ($total): int => (int) $total)
            ->all();

        return $counts;
    }
}
