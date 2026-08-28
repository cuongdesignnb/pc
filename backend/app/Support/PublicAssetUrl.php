<?php

namespace App\Support;

/**
 * Normalize asset URLs before they are exposed to a browser.
 *
 * Local development URLs can end up persisted by the admin media picker. They
 * are not reachable from the public storefront, so map them to the current
 * backend origin while preserving real CDN/external URLs.
 */
final class PublicAssetUrl
{
    private const LOCAL_HOSTS = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
    ];

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parts = parse_url($value);
        if ($parts === false) {
            return $value;
        }

        $path = (string) ($parts['path'] ?? '');
        $barePath = ltrim($path, '/');
        $host = strtolower((string) ($parts['host'] ?? ''));
        $isRelativeStoragePath = $host === ''
            && ($path === 'storage'
                || $path === '/storage'
                || str_starts_with($path, 'storage/')
                || str_starts_with($path, '/storage/'));
        $isBarePublicAssetPath = $host === ''
            && (str_starts_with($barePath, 'media/')
                || str_starts_with($barePath, 'icons/')
                || str_starts_with($barePath, 'thumbnails/'));
        $isLocalUrl = in_array($host, self::LOCAL_HOSTS, true);

        if (! $isRelativeStoragePath && ! $isBarePublicAssetPath && ! $isLocalUrl) {
            return $value;
        }

        if ($path === '') {
            return $value;
        }

        $path = '/'.ltrim($path, '/');
        if ($isBarePublicAssetPath) {
            $path = '/storage'.$path;
        }
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return url($path.$query);
    }
}
