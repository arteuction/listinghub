<?php

declare(strict_types=1);

namespace App\Actions\Publishing;

use App\Models\PreviewToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Issues a time-limited, unguessable preview token for a content resource.
 *
 * Any existing valid token for the same resource and creator is revoked
 * so that each editor holds at most one active link at a time.
 */
final class IssuePreviewToken
{
    /** Default lifetime: 48 hours. Configurable via $ttlHours. */
    public function handle(Model $resource, ?User $actor = null, int $ttlHours = 48): PreviewToken
    {
        // Revoke previous token for the same resource + creator
        PreviewToken::query()
            ->where('previewable_type', $resource->getMorphClass())
            ->where('previewable_id', $resource->getKey())
            ->where('created_by', $actor?->getKey())
            ->delete();

        return PreviewToken::query()->create([
            'token'            => Str::random(48),
            'previewable_type' => $resource->getMorphClass(),
            'previewable_id'   => $resource->getKey(),
            'created_by'       => $actor?->getKey(),
            'expires_at'       => now()->addHours($ttlHours),
        ]);
    }
}
