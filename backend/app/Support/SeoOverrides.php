<?php

namespace App\Support;

use App\Services\Seo\PublicUrlResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Per-row "Robots" and "Canonical" set in the admin SEO box of a product,
 * post or page. Null means the default (index,follow and the page's own URL).
 *
 * canonical_path in the public API stays the page's real path (the storefront
 * routes and redirects with it); an override only changes canonical_url and
 * robots, which go into the <link rel="canonical"> and <meta name="robots">.
 */
final class SeoOverrides
{
    public const DEFAULT_ROBOTS = 'index,follow';

    public const ROBOTS = ['index,follow', 'noindex,follow', 'noindex,nofollow', 'index,nofollow'];

    /** @return array<string, mixed> Validation rules for the admin forms. */
    public static function rules(bool $sometimes = false): array
    {
        return [
            // Rule::in, not "in:...": the values themselves contain commas.
            'meta_robots' => [...($sometimes ? ['sometimes'] : []), 'nullable', 'string', Rule::in(self::ROBOTS)],
            'canonical_url' => [...($sometimes ? ['sometimes'] : []), 'nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && self::normalizeCanonical((string) $value) === null) {
                    $fail('Canonical phải là link https://... đầy đủ hoặc đường dẫn trên website bắt đầu bằng "/" (không có ? hoặc #).');
                }
            }],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return ['meta_robots.in' => 'Robots chỉ nhận: '.implode(', ', self::ROBOTS).'.'];
    }

    /** Trim and blank → null before saving. */
    public static function normalizeInput(array $validated): array
    {
        if (array_key_exists('meta_robots', $validated)) {
            $validated['meta_robots'] = filled($validated['meta_robots']) ? (string) $validated['meta_robots'] : null;
        }
        if (array_key_exists('canonical_url', $validated)) {
            $validated['canonical_url'] = filled($validated['canonical_url'])
                ? self::normalizeCanonical((string) $validated['canonical_url'])
                : null;
        }

        return $validated;
    }

    /** An absolute https URL, or a site path starting with a single "/". */
    public static function normalizeCanonical(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            return null;
        }
        if (str_starts_with($value, '/')) {
            return str_starts_with($value, '//') || str_contains($value, '?') || str_contains($value, '#') ? null : $value;
        }
        $parts = parse_url($value);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || blank($parts['host'] ?? null)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return null;
        }

        return $value;
    }

    public static function robots(?string $value): string
    {
        return in_array($value, self::ROBOTS, true) ? $value : self::DEFAULT_ROBOTS;
    }

    public static function isNoindex(?string $value): bool
    {
        return str_starts_with(self::robots($value), 'noindex');
    }

    /** The canonical URL the page should declare: the override, else its own URL. */
    public static function canonicalUrl(?string $override, ?string $computedUrl): ?string
    {
        $override = filled($override) ? self::normalizeCanonical((string) $override) : null;
        if ($override === null) {
            return $computedUrl;
        }

        return str_starts_with($override, '/') ? app(PublicUrlResolver::class)->absolute($override) ?? $computedUrl : $override;
    }

    /** Rows that may be listed in a sitemap. */
    public static function scopeIndexable(Builder $query): Builder
    {
        $column = $query->getModel()->qualifyColumn('meta_robots');

        return $query->where(fn (Builder $inner) => $inner->whereNull($column)->orWhere($column, 'not like', 'noindex%'));
    }
}
