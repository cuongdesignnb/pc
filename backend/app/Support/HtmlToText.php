<?php

namespace App\Support;

/**
 * Reduce rich-editor HTML to plain text while keeping its reading structure:
 * headings and paragraphs become separate lines and list items become
 * bullets. The storefront renders the result with `white-space: pre-line`,
 * so no markup from the editor ever reaches the page.
 */
final class HtmlToText
{
    public static function convert(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);
        $text = preg_replace('/<li\b[^>]*>/i', "\n• ", $text);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/?(?:h[1-6]|p|div|ul|ol|blockquote|table|tr|section|article|figure)\b[^>]*>/i', "\n\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = array_map(
            fn (string $line) => trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line)),
            explode("\n", str_replace(["\r\n", "\r"], "\n", $text)),
        );
        $text = preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));
        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
