<?php

declare(strict_types=1);

namespace App\Actions\Publishing;

use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates or replaces the SEO metadata record for a content resource.
 *
 * Canonical paths must be relative (start with "/") or null.
 * Robots values are validated against the allowed set.
 * OG image references a path only — never a URL or base64 blob.
 */
final class UpsertSeoMeta
{
    private const ALLOWED_ROBOTS = [
        'index,follow',
        'noindex,nofollow',
        'noindex,follow',
        'index,nofollow',
    ];

    /**
     * @param array{
     *   meta_title?: string|null,
     *   meta_description?: string|null,
     *   robots?: string|null,
     *   canonical_path?: string|null,
     *   og?: array{title?: string, description?: string, image_path?: string}|null,
     * } $data
     */
    public function handle(Model $resource, array $data): SeoMeta
    {
        $this->validate($data);

        return DB::transaction(function () use ($resource, $data): SeoMeta {
            /** @var SeoMeta $meta */
            $meta = SeoMeta::query()->firstOrNew([
                'seoable_type' => $resource->getMorphClass(),
                'seoable_id' => $resource->getKey(),
            ]);

            $meta->fill([
                'meta_title' => isset($data['meta_title']) ? mb_substr((string) $data['meta_title'], 0, 120) : $meta->meta_title,
                'meta_description' => isset($data['meta_description']) ? mb_substr((string) $data['meta_description'], 0, 320) : $meta->meta_description,
                'robots' => $data['robots'] ?? $meta->robots ?? 'index,follow',
                'canonical_path' => $data['canonical_path'] ?? $meta->canonical_path,
                'og' => $data['og'] ?? $meta->og,
            ]);

            $meta->save();

            return $meta;
        });
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data): void
    {
        if (isset($data['robots']) && ! in_array($data['robots'], self::ALLOWED_ROBOTS, true)) {
            throw new InvalidArgumentException(
                'Invalid robots value. Allowed: '.implode(', ', self::ALLOWED_ROBOTS)
            );
        }

        if (isset($data['canonical_path'])) {
            $path = (string) $data['canonical_path'];
            if ($path !== '' && ! str_starts_with($path, '/')) {
                throw new InvalidArgumentException(
                    'Canonical path must be a relative path starting with "/".'
                );
            }
        }

        if (isset($data['og']['image_path'])) {
            $img = (string) $data['og']['image_path'];
            if (str_starts_with($img, 'data:') || str_starts_with($img, 'http')) {
                throw new InvalidArgumentException(
                    'OG image must be a server-side path, not a URL or base64 blob.'
                );
            }
        }
    }
}
