<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Replaces the full item tree for a menu in one transaction.
 *
 * Accepts a flat array of item descriptors with an optional `parent_index`
 * (0-based position in the same array) for one level of nesting. Items are
 * written in index order; parent rows are persisted before children.
 *
 * Shape of each item:
 *   ['label' => string, 'url' => string, 'open_in_new_tab' => bool, 'parent_index' => ?int]
 *
 * URL policy: relative paths starting with "/" are always allowed.
 * Absolute URLs are restricted to https:// or http://.
 */
final class SyncMenuItems
{
    /**
     * @param array<int, array{label: string, url: string, open_in_new_tab?: bool, parent_index?: int|null}> $items
     */
    public function handle(Menu $menu, array $items): void
    {
        foreach ($items as $i => $item) {
            $this->validateItem($item, $i);
        }

        DB::transaction(function () use ($menu, $items): void {
            MenuItem::query()->where('menu_id', $menu->id)->delete();

            $created = [];

            foreach ($items as $i => $item) {
                $parentId = null;

                if (isset($item['parent_index'])) {
                    $pi = (int) $item['parent_index'];
                    if ($pi >= $i) {
                        throw new InvalidArgumentException(
                            "Item at index {$i} references a parent_index ({$pi}) that does not precede it."
                        );
                    }
                    $parentId = $created[$pi]->id ?? null;
                }

                $created[$i] = MenuItem::query()->create([
                    'menu_id'         => $menu->id,
                    'parent_id'       => $parentId,
                    'label'           => trim($item['label']),
                    'url'             => $item['url'],
                    'open_in_new_tab' => (bool) ($item['open_in_new_tab'] ?? false),
                    'sort_order'      => ($i + 1) * 1000,
                ]);
            }
        });
    }

    /** @param array<string, mixed> $item */
    private function validateItem(array $item, int $index): void
    {
        if (! isset($item['label']) || trim((string) $item['label']) === '') {
            throw new InvalidArgumentException("Menu item at index {$index} is missing a label.");
        }

        if (! isset($item['url'])) {
            throw new InvalidArgumentException("Menu item at index {$index} is missing a url.");
        }

        $url = (string) $item['url'];

        // Relative paths and bare anchors are always fine
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! in_array(strtolower((string) $scheme), ['https', 'http'], true)) {
            throw new InvalidArgumentException(
                "Menu item at index {$index} has an invalid URL scheme. Use https://, http://, or a relative path."
            );
        }
    }
}
