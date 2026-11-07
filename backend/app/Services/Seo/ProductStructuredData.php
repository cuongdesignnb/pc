<?php

namespace App\Services\Seo;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Services\Checkout\ShippingCalculator;

/**
 * schema.org pieces of the product page that the storefront cannot work out
 * on its own: condition, seller, warranty, return policy, shipping and the
 * latest approved reviews. Price and availability stay on the storefront,
 * which knows the chosen variant.
 */
class ProductStructuredData
{
    public const USED = 'https://schema.org/UsedCondition';

    public const NEW = 'https://schema.org/NewCondition';

    public const REFURBISHED = 'https://schema.org/RefurbishedCondition';

    public function __construct(
        private readonly PublicUrlResolver $urls,
        private readonly ShippingCalculator $shipping,
    ) {}

    /** @return array<string, mixed> */
    public function forProduct(Product $product, int $displayPrice): array
    {
        $origin = $this->urls->absolute('/');

        return [
            'brand' => $this->brandName($product),
            'gtin' => $this->gtin($product->barcode),
            'mpn' => $this->mpn($product),
            'item_condition' => $this->condition($product),
            'price_valid_until' => $this->priceValidUntil(),
            'seller' => array_filter([
                '@type' => 'Organization',
                'name' => (string) (Setting::get('site_name') ?: 'Laptop Plus'),
                'url' => $origin,
            ]),
            'warranty' => $product->warranty_months > 0 ? [
                '@type' => 'WarrantyPromise',
                'durationOfWarranty' => ['@type' => 'QuantitativeValue', 'value' => (int) $product->warranty_months, 'unitCode' => 'MON'],
            ] : null,
            'return_policy' => $this->returnPolicy(),
            'shipping_details' => $this->shippingDetails($displayPrice),
            'reviews' => $this->reviews($product),
        ];
    }

    /**
     * The product's brand, or for products imported without one (most KIOT
     * laptops) the brand named by its category (laptop-cu-dell) or its name.
     */
    public function brandName(Product $product): ?string
    {
        if ($product->brand) {
            return $product->brand->name;
        }

        $brands = Brand::query()->where('is_active', true)->get(['name', 'slug']);
        $category = $product->category;
        for ($depth = 0; $category instanceof Category && $depth < 5; $depth++) {
            $tokens = explode('-', (string) $category->slug);
            $brand = $brands->first(fn (Brand $brand): bool => in_array((string) $brand->slug, $tokens, true));
            if ($brand) {
                return $brand->name;
            }
            $category = $category->parent;
        }

        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $product->name)) ?: [];
        $brand = $brands->first(fn (Brand $brand): bool => in_array(mb_strtolower((string) $brand->name), $words, true));

        return $brand?->name;
    }

    /** A barcode that is a real GTIN-8/12/13/14 (valid GS1 check digit), else null. */
    public function gtin(?string $barcode): ?string
    {
        $digits = trim((string) $barcode);
        if (! preg_match('/^(\d{8}|\d{12,14})$/', $digits)) {
            return null;
        }
        $sum = 0;
        $body = strrev(substr($digits, 0, -1));
        for ($i = 0; $i < strlen($body); $i++) {
            $sum += (int) $body[$i] * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === (int) substr($digits, -1) ? $digits : null;
    }

    /** The manufacturer part number from the product's specifications, when listed. */
    public function mpn(Product $product): ?string
    {
        foreach ($product->parsed_specifications as $spec) {
            if (preg_match('/^(mpn|part\s*(number|no\.?)|p\/n|mã\s*(nhà sản xuất|sản phẩm nhà sản xuất|part)|model\s*number)$/iu', trim((string) ($spec['label'] ?? ''))) && filled($spec['value'] ?? null)) {
                return mb_substr(trim((string) $spec['value']), 0, 70);
            }
        }

        return null;
    }

    /** Laptop cũ (the category tree or the name says "cũ") is used; else refurbished or new. */
    public function condition(Product $product): string
    {
        $names = [$product->name];
        $category = $product->category;
        for ($depth = 0; $category instanceof Category && $depth < 5; $depth++) {
            if (preg_match('/(^|-)cu($|-)/', (string) $category->slug)) {
                return self::USED;
            }
            $names[] = $category->name;
            $category = $category->parent;
        }
        foreach ($names as $name) {
            if (preg_match('/(^|[^\p{L}])cũ($|[^\p{L}])/iu', (string) $name)) {
                return self::USED;
            }
        }

        return $product->is_refurbished ? self::REFURBISHED : self::NEW;
    }

    /** The end of this year, or of next year during the last month. */
    private function priceValidUntil(): string
    {
        $end = now()->endOfYear();

        return ($end->diffInDays(now(), true) < 31 ? $end->addYear() : $end)->toDateString();
    }

    /** @return array<string, mixed> */
    private function returnPolicy(): array
    {
        $days = max(1, (int) (Setting::get('schema_return_days') ?: 7));

        return [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'VN',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => $days,
            'returnMethod' => ['https://schema.org/ReturnInStore', 'https://schema.org/ReturnByMail'],
            'returnFees' => (string) (Setting::get('schema_return_fees') ?: 'https://schema.org/FreeReturn'),
            'merchantReturnLink' => $this->urls->absolute((string) (Setting::get('schema_return_policy_path') ?: '/chinh-sach-giao-hang-doi-tra-va-hoan-tien')),
        ];
    }

    /** @return array<string, mixed> */
    private function shippingDetails(int $displayPrice): array
    {
        $free = $displayPrice > 0 && $displayPrice >= $this->shipping->freeThreshold();
        $range = fn (string $key, int $min, int $max): array => [
            '@type' => 'QuantitativeValue',
            'minValue' => (int) (Setting::get($key.'_min') ?? $min),
            'maxValue' => (int) (Setting::get($key.'_max') ?? $max),
            'unitCode' => 'DAY',
        ];

        return [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => $free ? 0 : $this->shipping->defaultFee(), 'currency' => 'VND'],
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'VN'],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => $range('schema_shipping_handling_days', 0, 1),
                'transitTime' => $range('schema_shipping_transit_days', 1, 4),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function reviews(Product $product): array
    {
        return Review::query()
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->with('user:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Review $review): array => array_filter([
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => $review->user?->name ?: ($review->guest_name ?: 'Khách hàng')],
                'datePublished' => $review->created_at?->toDateString(),
                'name' => $review->title ?: null,
                'reviewBody' => $review->body ?: null,
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int) $review->rating, 'bestRating' => 5, 'worstRating' => 1],
            ]))
            ->values()
            ->all();
    }
}
