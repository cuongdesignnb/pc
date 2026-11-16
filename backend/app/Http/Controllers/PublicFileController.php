<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fixed public link for a media file: /files/{public_slug}. The link stays the
 * same when the admin replaces the file ("Thay file"), so a driver or PDF link
 * shared with customers never breaks.
 */
class PublicFileController extends Controller
{
    public function show(string $slug): Response
    {
        $media = Media::query()->where('public_slug', $slug)->first();
        abort_unless($media && Storage::disk($media->disk)->exists($media->path), 404);

        $inline = str_starts_with((string) $media->mime_type, 'image/') && $media->mime_type !== 'image/svg+xml'
            || in_array($media->mime_type, ['application/pdf', 'video/mp4', 'video/webm'], true);

        return Storage::disk($media->disk)->response($media->path, $media->downloadName(), [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            // Short cache: a replaced file shows up within minutes on the same link.
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; media-src 'self'; sandbox",
        ], $inline ? 'inline' : 'attachment');
    }
}
