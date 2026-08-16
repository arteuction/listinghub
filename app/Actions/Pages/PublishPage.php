<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Publishes a page. If the page is the homepage it exclusively takes that
 * role (only one page may hold is_homepage = true at a time).
 */
final class PublishPage
{
    public function handle(Page $page, ?User $actor = null): Page
    {
        return DB::transaction(function () use ($page, $actor): Page {
            if ($page->is_homepage) {
                Page::query()
                    ->where('id', '!=', $page->id)
                    ->where('is_homepage', true)
                    ->update(['is_homepage' => false]);
            }

            $page->status      = PageStatus::Published;
            $page->published_at ??= now();
            $page->updated_by  = $actor?->getKey();
            $page->save();

            return $page;
        });
    }
}
