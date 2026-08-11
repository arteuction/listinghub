<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property ModerationStatus $status
 * @property string|null $document_path
 * @property string|null $document_disk
 * @property string|null $document_mime
 * @property int|null $document_size
 * @property string|null $document_sha256
 * @property string|null $document_original_name
 */
class ListingClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id', 'user_id', 'status', 'message',
        'document_path', 'document_disk', 'document_mime',
        'document_size', 'document_sha256', 'document_original_name',
    ];

    protected function casts(): array
    {
        return ['status' => ModerationStatus::class];
    }

    protected static function booted(): void
    {
        // A claim owns its document file: deleting the row must not leave an
        // orphaned proof-of-ownership document on the private disk.
        static::deleting(function (self $claim): void {
            $claim->deleteDocumentFile();
        });
    }

    public function hasDocument(): bool
    {
        return $this->document_path !== null && $this->document_disk !== null;
    }

    public function deleteDocumentFile(): void
    {
        if ($this->hasDocument()) {
            Storage::disk((string) $this->document_disk)->delete((string) $this->document_path);
        }
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
