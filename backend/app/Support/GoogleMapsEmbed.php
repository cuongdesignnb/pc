<?php

namespace App\Support;

/**
 * Accept the "Embed a map" iframe (or its src) copied from Google Maps and
 * keep only a Google Maps embed URL, so the storefront never renders an
 * arbitrary iframe or markup supplied through settings.
 */
final class GoogleMapsEmbed
{
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/is', $value, $match)) {
            $value = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $parts = parse_url($value);
        if (($parts['scheme'] ?? '') !== 'https') {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        $isEmbed = in_array($host, ['www.google.com', 'google.com'], true) && str_starts_with($path, '/maps/embed')
            || in_array($host, ['maps.google.com', 'www.google.com', 'google.com'], true)
                && str_starts_with($path, '/maps') && ($query['output'] ?? null) === 'embed';

        return $isEmbed ? $value : null;
    }
}
