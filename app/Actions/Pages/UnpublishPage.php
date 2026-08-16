<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use InvalidArgumentException;

final class UnpublishPage
{
    public function handle(Page $page, ?User $actor = null): Page
    {
        if ($page->is_system) {
            throw new InvalidArgumentException('System pages cannot be unpublished.');
        }

        $page->status     = PageStatus::Draft;
        $page->updated_by = $actor?->getKey();
        $page->save();

        return $page;
    }
}
