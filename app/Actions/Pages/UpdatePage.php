<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdatePage
{
    private const ALLOWED = ['title', 'slug', 'sort_order'];

    /** @param array<string, mixed> $changes */
    public function handle(Page $page, array $changes, ?User $actor = null): Page
    {
        if ($page->is_system && array_key_exists('slug', $changes)) {
            throw new InvalidArgumentException('System page slugs are immutable.');
        }

        $unknown = array_diff(array_keys($changes), self::ALLOWED);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unsupported page fields: '.implode(', ', $unknown));
        }

        return DB::transaction(function () use ($page, $changes, $actor): Page {
            if (isset($changes['sort_order'])) {
                $changes['sort_order'] = max(0, (int) $changes['sort_order']);
            }

            $page->fill($changes);
            $page->updated_by = $actor?->getKey();
            $page->save();

            return $page->fresh();
        });
    }
}
