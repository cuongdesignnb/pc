<?php

namespace App\Support;

/**
 * Parse admin-entered YouTube links into a safe, embeddable shape.
 *
 * Only real YouTube hosts and 11-character video ids are accepted, so the
 * storefront can render an iframe without trusting arbitrary URLs.
 */
final class YouTubeVideo
{
    private const HOSTS = [
        'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
        'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be',
    ];

    private const ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    public static function id(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($host, self::HOSTS, true)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', (string) ($parts['path'] ?? ''))));
        if ($host === 'youtu.be') {
            $candidate = $segments[0] ?? '';
        } elseif (in_array($segments[0] ?? '', ['embed', 'shorts', 'live', 'v'], true)) {
            $candidate = $segments[1] ?? '';
        } else {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $candidate = is_string($query['v'] ?? null) ? $query['v'] : '';
        }

        return preg_match(self::ID_PATTERN, $candidate) ? $candidate : null;
    }

    public static function canonicalUrl(?string $url): ?string
    {
        $id = self::id($url);

        return $id === null ? null : 'https://www.youtube.com/watch?v='.$id;
    }

    /** @return array{id:string,watch_url:string,embed_url:string,thumbnail_url:string}|null */
    public static function toArray(?string $url): ?array
    {
        $id = self::id($url);
        if ($id === null) {
            return null;
        }

        return [
            'id' => $id,
            'watch_url' => 'https://www.youtube.com/watch?v='.$id,
            'embed_url' => 'https://www.youtube-nocookie.com/embed/'.$id,
            'thumbnail_url' => 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg',
        ];
    }
}
