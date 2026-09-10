<?php

namespace Tests\Unit;

use App\Exceptions\SeoSlugException;
use App\Services\Seo\VietnameseSlugNormalizer;
use Tests\TestCase;

class VietnameseSlugNormalizerTest extends TestCase
{
    /** @return array<string, array{0:string,1:string}> */
    public static function goldenSources(): array
    {
        return [
            'screen' => ['Màn hình', 'man-hinh'],
            'graphics' => ['Đồ họa & Thiết kế', 'do-hoa-thiet-ke'],
            'ssd' => ['Ổ cứng SSD 1TB', 'o-cung-ssd-1tb'],
            'keyboard' => ['Bàn_phím---cơ', 'ban-phim-co'],
            'laptop' => [' Laptop  ASUS   TUF F15 ', 'laptop-asus-tuf-f15'],
            'intel' => ['Intel Core i5-12400F', 'intel-core-i5-12400f'],
            'ram' => ['RAM DDR5 32GB (2x16GB)', 'ram-ddr5-32gb-2x16gb'],
            'nvme' => ['SSD M.2 NVMe PCIe 4.0', 'ssd-m-2-nvme-pcie-4-0'],
            'uppercase' => ['ĐIỆN TOÁN', 'dien-toan'],
            'nbsp-hyphen' => ["Wi\u{2011}Fi 6E", 'wi-fi-6e'],
            'slash' => ['Thiết kế 3D / Đồ họa', 'thiet-ke-3d-do-hoa'],
            'full-width' => ['ＰＣ ２０２６', 'pc-2026'],
            'nfc' => ['Điện toán dạng NFC', 'dien-toan-dang-nfc'],
            'zero-width' => ["Điện\u{200B}toán", 'dien-toan'],
            'existing' => ['Card màn hình', 'card-man-hinh'],
            'danang' => ['Đà Nẵng', 'da-nang'],
        ];
    }

    /**
     * The examples are intentionally kept as a data provider so every
     * approved source/expected pair is visible in a failing test report.
     *
     * @dataProvider goldenSources
     */
    public function test_approved_golden_sources_are_normalized_deterministically(string $source, string $expected): void
    {
        $normalizer = app(VietnameseSlugNormalizer::class);

        $this->assertSame($expected, $normalizer->normalize($source));
        $this->assertSame($expected, $normalizer->normalize($expected));
    }

    public function test_golden_vietnamese_sources_are_ascii_and_stable(): void
    {
        $normalizer = app(VietnameseSlugNormalizer::class);

        $this->assertSame('card-man-hinh', $normalizer->normalize('Card màn hình'));
        $this->assertSame('da-nang', $normalizer->normalize('Đà Nẵng'));
        $this->assertSame('pc-gaming-2025', $normalizer->normalize('PC — Gaming 2025'));
        $this->assertSame(
            $normalizer->normalize("a\u{0301}"),
            $normalizer->normalize('á'),
        );
    }

    public function test_custom_slug_is_validated_without_rewriting(): void
    {
        $normalizer = app(VietnameseSlugNormalizer::class);

        $this->assertSame('card-man-hinh', $normalizer->validateCustom('card-man-hinh'));
        try {
            $normalizer->validateCustom('Card màn hình');
            $this->fail('Expected invalid custom slug to fail.');
        } catch (SeoSlugException $exception) {
            $this->assertSame('SLUG_NOT_SEGMENT', $exception->errorCode);
        }
    }

    public function test_reserved_and_invalid_sources_fail_closed(): void
    {
        $normalizer = app(VietnameseSlugNormalizer::class);

        $this->assertTrue($normalizer->isReserved('api'));
        $this->assertTrue($normalizer->isReserved('sitemap.xml'));

        try {
            $normalizer->normalize('😀😀');
            $this->fail('Expected empty slug source to fail.');
        } catch (SeoSlugException $exception) {
            $this->assertSame('EMPTY_SLUG', $exception->errorCode);
        }
    }

    public function test_non_string_input_is_rejected_instead_of_stringified(): void
    {
        try {
            app(VietnameseSlugNormalizer::class)->normalize(['name' => 'not-a-slug-source']);
            $this->fail('Expected non-string slug source to fail.');
        } catch (SeoSlugException $exception) {
            $this->assertSame('INVALID_SOURCE', $exception->errorCode);
        }
    }

    public function test_source_that_would_produce_a_slug_over_160_characters_is_rejected(): void
    {
        $this->expectException(SeoSlugException::class);
        $this->expectExceptionMessage('160');

        app(VietnameseSlugNormalizer::class)->normalize(str_repeat('a ', 81));
    }

    public function test_invalid_utf8_source_is_rejected(): void
    {
        try {
            app(VietnameseSlugNormalizer::class)->normalize("\xC3\x28");
            $this->fail('Expected invalid UTF-8 source to fail.');
        } catch (SeoSlugException $exception) {
            $this->assertSame('INVALID_UTF8', $exception->errorCode);
        }
    }

    public function test_custom_slug_rejects_urls_paths_queries_fragments_and_encoded_separators(): void
    {
        $normalizer = app(VietnameseSlugNormalizer::class);

        foreach (['https://example.com/x', '../x', 'x/y', 'x?y', 'x#y', 'x%2Fy', "x\r\ny"] as $value) {
            try {
                $normalizer->validateCustom($value);
                $this->fail('Expected invalid custom slug to fail: '.$value);
            } catch (SeoSlugException $exception) {
                $this->assertContains($exception->errorCode, ['SLUG_NOT_SEGMENT', 'SLUG_FORMAT', 'SLUG_WHITESPACE', 'CONTROL_CHARACTER']);
            }
        }
    }
}
