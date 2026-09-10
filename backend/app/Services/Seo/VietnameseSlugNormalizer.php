<?php

namespace App\Services\Seo;

use App\Exceptions\SeoSlugException;
use Illuminate\Support\Str;

/**
 * The single slug policy used by admin, imports, KIOT and SEO migration tools.
 *
 * Public slugs are persisted values. This class only creates a value when a
 * new entity is written; GET requests must never call it to invent a route.
 */
class VietnameseSlugNormalizer
{
    public const POLICY_VERSION = 'vn-v1';

    public const MAX_LENGTH = 160;

    public const MAX_SOURCE_BYTES = 4096;

    /** @var list<string> */
    private const RESERVED_SEGMENTS = [
        'san-pham', 'danh-muc', 'tin-tuc', 'thuong-hieu', 'tim-kiem', 'cau-hinh',
        'gioi-thieu', 'lien-he', 'bao-hanh', 'van-chuyen', 'dang-nhap', 'dang-ky',
        'tai-khoan', 'gio-hang', 'thanh-toan', 'don-hang', 'yeu-thich', 'products',
        'categories', 'blog', 'search', 'cart', 'checkout', 'account', 'orders',
        'auth', 'api', 'admin', 'payment', 'payments', 'webhooks', 'sanctum',
        'feeds', 'storage', 'assets', 'build', 'health', 'robots.txt', 'sitemap.xml',
        'sitemaps', '_nuxt', '_ipx', '.well-known',
    ];

    public function normalize(mixed $source): string
    {
        $this->assertText($source);

        $value = $source;
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_KC) ?: $value;
            $value = \Normalizer::normalize($value, \Normalizer::FORM_D) ?: $value;
        }

        // Unicode separators and zero-width separators are word breaks. Keep
        // them as spaces before transliteration so words do not concatenate.
        $value = preg_replace('/[\p{Z}\p{Cf}]+/u', ' ', $value) ?? $value;
        // Preserve Unicode dash punctuation as a word separator. Some
        // transliteration tables drop non-breaking hyphens entirely.
        $value = preg_replace('/[\p{Pd}]+/u', '-', $value) ?? $value;
        // Vietnamese đ is not decomposed by every ICU build.
        $value = str_replace(['đ', 'Đ'], ['d', 'D'], $value);
        $value = preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
        $value = Str::ascii($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        if ($value === '') {
            throw new SeoSlugException('Không thể tạo slug từ nội dung đã nhập.', 'EMPTY_SLUG');
        }
        if (strlen($value) > self::MAX_LENGTH) {
            throw new SeoSlugException('Slug dài quá '.self::MAX_LENGTH.' ký tự ASCII.', 'SLUG_TOO_LONG');
        }

        return $value;
    }

    /**
     * Validate an editor-provided slug without silently changing it.
     */
    public function validateCustom(mixed $slug): string
    {
        $this->assertText($slug);

        if ($slug !== trim($slug)) {
            throw new SeoSlugException('Slug không được có khoảng trắng ở đầu hoặc cuối.', 'SLUG_WHITESPACE');
        }
        if (preg_match('~(?:https?:)?//~i', $slug) === 1
            || preg_match('~[\\\\/?#%\x00-\x1F\x7F]~', $slug) === 1
            || preg_match('/[\x80-\xFF]/', $slug) === 1) {
            throw new SeoSlugException('Slug chỉ được chứa một segment ASCII, không URL/query/fragment.', 'SLUG_NOT_SEGMENT');
        }
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new SeoSlugException('Slug phải có dạng chữ thường ASCII và dấu gạch nối.', 'SLUG_FORMAT');
        }
        if (strlen($slug) > self::MAX_LENGTH) {
            throw new SeoSlugException('Slug dài quá '.self::MAX_LENGTH.' ký tự ASCII.', 'SLUG_TOO_LONG');
        }

        return $slug;
    }

    public function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::RESERVED_SEGMENTS, true);
    }

    /** @return list<string> */
    public function reservedSegments(): array
    {
        return self::RESERVED_SEGMENTS;
    }

    private function assertText(mixed $value): void
    {
        if (! is_string($value)) {
            throw new SeoSlugException('Slug source phải là chuỗi văn bản.', 'INVALID_SOURCE');
        }
        if ($value === '') {
            throw new SeoSlugException('Slug source không được rỗng.', 'EMPTY_SOURCE');
        }
        if (preg_match('//u', $value) !== 1) {
            throw new SeoSlugException('Slug source phải là UTF-8 hợp lệ.', 'INVALID_UTF8');
        }
        if (strlen($value) > self::MAX_SOURCE_BYTES) {
            throw new SeoSlugException('Slug source vượt quá giới hạn đầu vào.', 'SOURCE_TOO_LONG');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new SeoSlugException('Slug source không được chứa ký tự điều khiển.', 'CONTROL_CHARACTER');
        }
    }
}
