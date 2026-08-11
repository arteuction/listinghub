<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ListingClaim;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Staff-only download of a claim's proof-of-ownership document.
 *
 * The file lives on a private disk — this endpoint is the ONLY way to reach
 * it, the route sits behind auth + the admin permission gate, and the
 * response is always an attachment with nosniff so the browser saves the
 * bytes rather than rendering them in the admin origin.
 */
class ClaimDocumentController extends Controller
{
    public function download(ListingClaim $claim): StreamedResponse
    {
        abort_unless($claim->hasDocument(), Response::HTTP_NOT_FOUND);

        $disk = (string) $claim->document_disk;
        $path = (string) $claim->document_path;

        abort_unless(Storage::disk($disk)->exists($path), Response::HTTP_NOT_FOUND);

        return Storage::disk($disk)->download(
            $path,
            $claim->document_original_name ?? basename($path),
            [
                'Content-Type' => $claim->document_mime ?? 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
