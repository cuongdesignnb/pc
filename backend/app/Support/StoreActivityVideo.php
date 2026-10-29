<?php

namespace App\Support;

/**
 * Turn an admin-entered store video link into something the storefront can
 * play: a YouTube or Facebook embed, or a direct MP4/WebM file (for example
 * one uploaded to the Media library). Any other URL is rejected.
 */
final class StoreActivityVideo
{
    private const FACEBOOK_HOSTS = ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'web.facebook.com', 'fb.watch', 'www.fb.watch'];

    /** @return array{provider:string,embed_url:?string,file_url:?string,thumbnail_url:?string}|null */
    public static function resolve(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if ($youtube = YouTubeVideo::toArray($url)) {
            return ['provider' => 'youtube', 'embed_url' => $youtube['embed_url'], 'file_url' => null, 'thumbnail_url' => $youtube['thumbnail_url']];
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($scheme === 'https' && in_array($host, self::FACEBOOK_HOSTS, true)) {
            return [
                'provider' => 'facebook',
                'embed_url' => 'https://www.facebook.com/plugins/video.php?href='.rawurlencode($url).'&show_text=false',
                'file_url' => null,
                'thumbnail_url' => null,
            ];
        }

        $isFile = preg_match('/\.(mp4|webm)$/i', (string) ($parts['path'] ?? '')) === 1;
        $isAllowedLocation = $host === '' || $scheme === 'https';
        if ($isFile && $isAllowedLocation) {
            return ['provider' => 'file', 'embed_url' => null, 'file_url' => PublicAssetUrl::normalize($url), 'thumbnail_url' => null];
        }

        return null;
    }
}
