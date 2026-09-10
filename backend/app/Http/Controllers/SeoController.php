<?php

namespace App\Http\Controllers;

use App\Services\Seo\IndexabilityPolicy;
use App\Services\Seo\PublicUrlResolver;
use App\Services\Seo\SitemapExportService;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemapIndex(SitemapExportService $sitemaps): Response
    {
        return $this->xmlResponse($sitemaps->indexXml());
    }

    public function sitemapShard(string $shard, SitemapExportService $sitemaps): Response
    {
        $xml = $sitemaps->shardXml($shard);
        abort_if($xml === null, 404);

        return $this->xmlResponse($xml);
    }

    public function robots(PublicUrlResolver $urls, IndexabilityPolicy $policy): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
        ];
        foreach ((array) config('seo.private_paths', []) as $path) {
            $path = '/'.trim((string) $path, '/');
            if ($policy->isPrivatePath($path)) {
                $lines[] = 'Disallow: '.$path.'/';
            }
        }
        $lines[] = 'Sitemap: '.$urls->requireOrigin().'/sitemap.xml';

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function xmlResponse(string $xml): Response
    {
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
