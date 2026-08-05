<?php

namespace App\Services\Catalog\Pricing;

use App\Data\Catalog\CatalogProductData;
use App\Models\CatalogChannelPriceSetting;
use App\Models\CatalogPriceBook;
use App\Models\Product;

class CatalogChannelPriceResolver
{
    private ?array $settings = null;

    private ?array $activeBooks = null;

    public function __construct(private readonly CatalogPriceResolver $prices) {}

    public function setting(string $channel): CatalogChannelPriceSetting
    {
        $this->settings ??= CatalogChannelPriceSetting::query()->get()->keyBy('channel')->all();

        return ($this->settings[$channel] ?? null)
            ?? new CatalogChannelPriceSetting([
                'channel' => $channel,
                'price_source' => 'retail_price',
                'fallback_policy' => 'none',
                'is_enabled' => false,
            ]);
    }

    /** @return array{value:?int,source:string,fallback_used:bool,issue:?string} */
    public function resolve(Product $product, string $channel): array
    {
        $setting = $this->setting($channel);
        $values = $this->prices->forProduct($product);
        $source = trim((string) $setting->price_source);
        $value = null;
        $issue = null;

        if ($source === 'retail_price') {
            $value = $values['retail_price'];
        } elseif ($source === 'selected_price') {
            $value = $values['selected_price'];
        } elseif (str_starts_with($source, 'price_book:')) {
            $bookId = (int) substr($source, strlen('price_book:'));
            $this->activeBooks ??= CatalogPriceBook::query()->where('is_active', true)->pluck('id')->flip()->all();
            $book = $bookId > 0 && isset($this->activeBooks[$bookId]);
            if (! $book) {
                $issue = 'PRICE_SOURCE_UNAVAILABLE';
            } else {
                $value = $values['price_books'][$bookId] ?? null;
            }
        } elseif ($source === 'all_price_books') {
            $issue = 'PRICE_SOURCE_UNAVAILABLE';
        } else {
            $issue = 'PRICE_SOURCE_UNAVAILABLE';
        }

        if ($value === null || $value < 0) {
            $issue ??= 'PRICE_BOOK_VALUE_MISSING';
            $fallback = trim((string) $setting->fallback_policy);
            if ($fallback === 'retail_price') {
                return ['value' => $values['retail_price'], 'source' => $source, 'fallback_used' => true, 'issue' => 'PRICE_FALLBACK_USED'];
            }
            if ($fallback === 'selected_price' && $values['selected_price'] !== null) {
                return ['value' => $values['selected_price'], 'source' => $source, 'fallback_used' => true, 'issue' => 'PRICE_FALLBACK_USED'];
            }

            return ['value' => null, 'source' => $source, 'fallback_used' => false, 'issue' => $issue];
        }

        return ['value' => (int) $value, 'source' => $source, 'fallback_used' => false, 'issue' => null];
    }

    /** @return array{value:?int,source:string,fallback_used:bool,issue:?string} */
    public function resolveData(CatalogProductData $product, string $channel): array
    {
        $setting = $this->setting($channel);
        $source = trim((string) $setting->price_source);
        $value = match (true) {
            $source === 'retail_price' => $product->price,
            $source === 'selected_price' => $product->selectedPrice,
            str_starts_with($source, 'price_book:') => data_get($product->priceBooks, (int) substr($source, strlen('price_book:'))),
            default => null,
        };
        $issue = $value === null ? ($source === 'all_price_books' ? 'PRICE_SOURCE_UNAVAILABLE' : 'PRICE_BOOK_VALUE_MISSING') : null;
        if ($value === null) {
            if ($setting->fallback_policy === 'retail_price') {
                return ['value' => $product->price, 'source' => $source, 'fallback_used' => true, 'issue' => 'PRICE_FALLBACK_USED'];
            }
            if ($setting->fallback_policy === 'selected_price' && $product->selectedPrice !== null) {
                return ['value' => $product->selectedPrice, 'source' => $source, 'fallback_used' => true, 'issue' => 'PRICE_FALLBACK_USED'];
            }
        }

        return ['value' => $value === null ? null : max(0, (int) $value), 'source' => $source, 'fallback_used' => false, 'issue' => $issue];
    }

    /** @return array<string,array{value:?int,source:string,fallback_used:bool,issue:?string}> */
    public function resolveAll(Product $product): array
    {
        $resolved = [];
        foreach (CatalogChannelPriceSetting::CHANNELS as $channel) {
            $resolved[$channel] = $this->resolve($product, $channel);
        }

        return $resolved;
    }
}
