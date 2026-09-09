<?php

namespace App\Services\News;

use App\Support\PublicAssetUrl;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

final class ArticleContentSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'mark',
        'ul', 'ol', 'li', 'blockquote', 'a', 'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'code', 'pre',
        'br', 'hr', 'div', 'span', 'iframe',
    ];

    /** @var list<string> */
    private const DROP_TAGS = [
        'script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea',
        'select', 'option', 'meta', 'link', 'base', 'svg', 'math', 'canvas',
    ];

    /**
     * @return array{body: string, toc: list<array{id: string, level: int, title: string}>}
     */
    public function sanitizeAndBuildToc(?string $html, ?string $imageAltFallback = null): array
    {
        $html = trim((string) $html);
        if ($html === '') {
            return ['body' => '', 'toc' => []];
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="article-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('article-root');
        if (! $root instanceof DOMElement) {
            return ['body' => '', 'toc' => []];
        }

        $toc = [];
        $usedIds = [];
        $this->sanitizeChildren($root, $toc, $usedIds, $imageAltFallback);

        $body = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $body .= $document->saveHTML($child);
        }

        return ['body' => $body, 'toc' => $toc];
    }

    public function sanitize(?string $html, ?string $imageAltFallback = null): string
    {
        return $this->sanitizeAndBuildToc($html, $imageAltFallback)['body'];
    }

    /**
     * @param list<array{id: string, level: int, title: string}> $toc
     * @param array<string, int> $usedIds
     */
    private function sanitizeChildren(DOMNode $parent, array &$toc, array &$usedIds, ?string $imageAltFallback): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_TAGS, true)) {
                $parent->removeChild($child);
                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->sanitizeChildren($child, $toc, $usedIds, $imageAltFallback);
                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);
                continue;
            }

            if ($tag === 'img' && ! $this->sanitizeImage($child, $imageAltFallback)) {
                $parent->removeChild($child);
                continue;
            }
            if ($tag === 'iframe' && ! $this->sanitizeIframe($child)) {
                $parent->removeChild($child);
                continue;
            }

            $this->sanitizeAttributes($child, $tag);

            if (in_array($tag, ['h2', 'h3', 'h4'], true)) {
                $title = trim((string) preg_replace('/\s+/u', ' ', $child->textContent ?? ''));
                if ($title !== '') {
                    $id = $this->uniqueHeadingId($title, $usedIds);
                    $child->setAttribute('id', $id);
                    $toc[] = [
                        'id' => $id,
                        'level' => (int) substr($tag, 1),
                        'title' => $title,
                    ];
                }
            }

            $this->sanitizeChildren($child, $toc, $usedIds, $imageAltFallback);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = match ($tag) {
            'a' => ['href', 'target', 'rel', 'title'],
            'img' => ['src', 'alt', 'title', 'width', 'height', 'loading', 'decoding'],
            'iframe' => ['src', 'title', 'width', 'height', 'allow', 'allowfullscreen', 'loading', 'referrerpolicy'],
            'table' => ['summary'],
            'th', 'td' => ['colspan', 'rowspan', 'scope'],
            'div' => ['data-youtube-video'],
            default => [],
        };

        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! in_array(strtolower($attribute->name), $allowed, true)) {
                $element->removeAttributeNode($attribute);
            }
        }

        if ($tag === 'a') {
            $href = $this->safeUrl($element->getAttribute('href'));
            if ($href === null) {
                $element->removeAttribute('href');
            } else {
                $element->setAttribute('href', $href);
            }

            $target = $element->getAttribute('target');
            if ($target !== '_blank') {
                $element->removeAttribute('target');
                $element->removeAttribute('rel');
            } else {
                $element->setAttribute('rel', $this->safeRel($element->getAttribute('rel')));
            }
        }

        foreach (['width', 'height', 'colspan', 'rowspan'] as $numericAttribute) {
            if ($element->hasAttribute($numericAttribute)) {
                $value = filter_var($element->getAttribute($numericAttribute), FILTER_VALIDATE_INT);
                if ($value === false || $value < 1 || $value > 4000) {
                    $element->removeAttribute($numericAttribute);
                } else {
                    $element->setAttribute($numericAttribute, (string) $value);
                }
            }
        }
    }

    private function sanitizeImage(DOMElement $image, ?string $fallbackAlt): bool
    {
        $src = $this->safeUrl($image->getAttribute('src'), true);
        if ($src === null) {
            return false;
        }

        $image->setAttribute('src', $src);
        if (! $image->hasAttribute('alt') || trim($image->getAttribute('alt')) === '') {
            if (filled($fallbackAlt)) {
                $image->setAttribute('alt', trim((string) $fallbackAlt));
            } else {
                $image->setAttribute('alt', '');
            }
        }

        return true;
    }

    private function sanitizeIframe(DOMElement $iframe): bool
    {
        $src = $this->safeUrl($iframe->getAttribute('src'));
        if ($src === null) {
            return false;
        }

        $host = strtolower((string) parse_url($src, PHP_URL_HOST));
        $allowedHosts = [
            'youtube.com', 'www.youtube.com',
            'youtube-nocookie.com', 'www.youtube-nocookie.com',
            'youtu.be',
        ];
        if (! in_array($host, $allowedHosts, true)) {
            return false;
        }

        $path = (string) parse_url($src, PHP_URL_PATH);
        if ($host !== 'youtu.be' && ! str_contains($path, '/embed/')) {
            return false;
        }

        $iframe->setAttribute('src', $src);

        return true;
    }

    private function safeUrl(?string $value, bool $image = false): ?string
    {
        $value = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' || str_starts_with($value, '//')) {
            return null;
        }

        if (str_starts_with($value, '#') || (! str_contains($value, ':') && ! str_starts_with($value, 'data:'))) {
            return $image ? PublicAssetUrl::normalize($value) : $value;
        }

        if ($image && preg_match('/^data:image\/(?:png|jpe?g|gif|webp);base64,[a-z0-9+\/=\r\n]+$/i', $value)) {
            return $value;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (! in_array($scheme, $image ? ['http', 'https'] : ['http', 'https', 'mailto', 'tel'], true)) {
            return null;
        }

        return $image ? (PublicAssetUrl::normalize($value) ?? $value) : $value;
    }

    private function safeRel(?string $value): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim((string) $value))) ?: [];
        $tokens = array_values(array_intersect($tokens, ['noopener', 'noreferrer', 'nofollow']));
        $tokens = array_values(array_unique(array_merge($tokens, ['noopener', 'noreferrer'])));

        return implode(' ', $tokens);
    }

    /** @param array<string, int> $usedIds */
    private function uniqueHeadingId(string $title, array &$usedIds): string
    {
        $base = Str::slug($title) ?: 'section';
        $usedIds[$base] = ($usedIds[$base] ?? 0) + 1;
        $suffix = $usedIds[$base] > 1 ? '-'.$usedIds[$base] : '';

        return $base.$suffix;
    }
}
