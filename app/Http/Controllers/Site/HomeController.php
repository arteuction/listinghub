<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\ContentBlockStatus;
use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Region;
use App\Services\Catalog\PublicListingQuery;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly PublicListingQuery $catalog) {}

    public function index(): View
    {
        $homePage = Page::query()
            ->where('is_homepage', true)
            ->where('status', PageStatus::Published)
            ->first();

        $homeBlocks = $homePage
            ? $homePage->contentBlocks()
                ->where('status', ContentBlockStatus::Published)
                ->orderBy('sort_order')
                ->get()
            : collect();

        return view('site.home', [
            'featured' => $this->catalog->build(['featured' => true])->limit(8)->get(),
            'latest' => $this->catalog->build([])->limit(8)->get(),
            'categories' => Category::query()
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'regions' => Region::query()->orderBy('name')->get(),
            'homeBlocks' => $homeBlocks,
        ]);
    }
}
