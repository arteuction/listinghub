<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreatePage
{
    public function handle(
        string $title,
        string $slug,
        ?User $actor = null,
        bool $isHomepage = false,
        int $sortOrder = 1000,
    ): Page {
        return DB::transaction(function () use ($title, $slug, $actor, $isHomepage, $sortOrder): Page {
            if ($isHomepage) {
                Page::query()->where('is_homepage', true)->update(['is_homepage' => false]);
            }

            return Page::query()->create([
                'uuid' => (string) Str::uuid(),
                'slug' => $slug,
                'title' => $title,
                'is_system' => false,
                'is_homepage' => $isHomepage,
                'status' => PageStatus::Draft,
                'sort_order' => max(0, $sortOrder),
                'created_by' => $actor?->getKey(),
                'updated_by' => $actor?->getKey(),
            ]);
        });
    }
}
