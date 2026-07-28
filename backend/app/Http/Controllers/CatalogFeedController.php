<?php

namespace App\Http\Controllers;

use App\Models\CatalogChannelConnection;
use App\Services\Catalog\CatalogChannelManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class CatalogFeedController extends Controller
{
    public function __construct(private readonly CatalogChannelManager $channels) {}

    public function google(Request $request): Response
    {
        return $this->serve(
            $request,
            CatalogChannelConnection::GOOGLE_MERCHANT,
            (string) config('catalog.google_merchant.artifact'),
            'application/xml; charset=UTF-8',
        );
    }

    public function meta(Request $request): Response
    {
        return $this->serve(
            $request,
            CatalogChannelConnection::META_CATALOG,
            (string) config('catalog.meta_catalog.artifact'),
            'text/csv; charset=UTF-8',
        );
    }

    private function serve(Request $request, string $channel, string $artifact, string $contentType): Response
    {
        abort_unless($this->channels->feedTokenMatches($channel, $request->query('token')), 404);
        $path = Storage::disk((string) config('catalog.feed_disk', 'local'))
            ->path(trim((string) config('catalog.feed_directory', 'catalog-feeds'), '/').'/'.$artifact);
        abort_unless(is_file($path), 404);

        $etag = '"'.hash_file('sha256', $path).'"';
        if (hash_equals($etag, (string) $request->header('If-None-Match'))) {
            return response('', 304)->withHeaders(['ETag' => $etag]);
        }

        return (new BinaryFileResponse($path, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age='.(int) config('catalog.feed_cache_seconds', 900),
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', filemtime($path)).' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ]))->setContentDisposition('inline', $artifact);
    }
}
