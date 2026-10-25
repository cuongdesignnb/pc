<?php

namespace App\Services\Menus;

use App\Models\Page;
use App\Services\Seo\PublicUrlResolver;
use App\Services\Seo\SlugRedirectService;

class FooterPolicyLinks
{
    public function __construct(
        private readonly PublicUrlResolver $urls,
        private readonly SlugRedirectService $redirects,
    ) {}

    /** @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public function appendTo(array $items): array
    {
        $seenPages = [];
        $items = $this->visibleConfiguredItems($items, $seenPages);
        $links = [];

        foreach (Page::query()->active()->orderBy('id')->get(['id', 'title', 'slug']) as $page) {
            $path = $this->urls->pagePathForSlug((string) $page->slug);
            if (isset($seenPages[$page->id]) || trim((string) $page->title) === ''
                || $path !== '/'.$page->slug
                || $this->redirects->categoryBySlug((string) $page->slug) !== null) {
                continue;
            }

            $links[] = $this->item(-$page->id, trim($page->title), $path, 'page');
        }

        if ($links === []) {
            return $items;
        }

        foreach ($items as $index => $item) {
            if (! empty($item['children']) && mb_strtolower(trim($item['title'])) === 'chính sách') {
                $items[$index]['children'] = array_merge($item['children'], $links);

                return $items;
            }
        }

        // The existing storefront treats every root item as a column when any
        // item has children. Preserve a previously flat menu as one column.
        if ($items !== [] && ! array_filter($items, fn (array $item): bool => ! empty($item['children']))) {
            $items = [$this->item(-2, 'Liên kết', null, 'group', $items)];
        }

        $items[] = $this->item(-1, 'CHÍNH SÁCH', null, 'group', $links);

        return $items;
    }

    /** @param list<array<string, mixed>> $items
     * @param  array<int, true>  $seenPages
     * @return list<array<string, mixed>>
     */
    private function visibleConfiguredItems(array $items, array &$seenPages): array
    {
        $visible = [];
        foreach ($items as $item) {
            $page = $this->configuredPage($item['url'] ?? null);
            if ($page && ! $page->is_active) {
                continue;
            }
            if (! empty($item['children'])) {
                $item['children'] = $this->visibleConfiguredItems($item['children'], $seenPages);
                if ($item['children'] === []) {
                    continue;
                }
            }
            if ($page) {
                $seenPages[$page->id] = true;
            }
            $visible[] = $item;
        }

        return $visible;
    }

    private function configuredPage(mixed $url): ?Page
    {
        if (! is_string($url)) {
            return null;
        }
        $parts = parse_url(trim($url));
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        if (isset($parts['host'])) {
            $origin = parse_url($this->urls->origin() ?? '');
            if (! is_array($origin)
                || strtolower($parts['host']) !== strtolower($origin['host'] ?? '')
                || ($parts['port'] ?? null) !== ($origin['port'] ?? null)
                || (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true))) {
                return null;
            }
        } elseif (isset($parts['scheme'])) {
            return null;
        }

        $slug = trim($parts['path'] ?? '', '/');
        if ($this->urls->pagePathForSlug($slug) === null || $this->redirects->categoryBySlug($slug) !== null) {
            return null;
        }

        return $this->redirects->pageBySlug($slug);
    }

    /** @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private function item(int $id, string $title, ?string $url, string $type, array $children = []): array
    {
        return [
            // Synthetic negative IDs cannot collide with persisted menu IDs.
            'id' => $id, 'title' => $title, 'url' => $url, 'type' => $type,
            'icon' => null, 'badge_text' => null, 'badge_color' => null,
            'css_class' => null, 'target' => '_self', 'is_mega' => false,
            'mega_columns' => 1, 'description' => null, 'image' => null,
            'children' => $children,
        ];
    }
}
