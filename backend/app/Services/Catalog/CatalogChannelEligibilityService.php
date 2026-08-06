<?php

namespace App\Services\Catalog;

use App\Data\Catalog\CatalogProductData;
use App\Models\CatalogChannelConnection;
use App\Services\Catalog\Pricing\CatalogChannelPriceSettingsService;
use App\Services\Catalog\Pricing\CatalogPriceValidationService;

class CatalogChannelEligibilityService
{
    public function __construct(
        private readonly CatalogProductValidator $validator,
        private readonly CatalogChannelPriceSettingsService $settings,
        private readonly CatalogPriceValidationService $priceValidation,
    ) {}

    /** @return array{eligible:bool,valid:bool,errors:list<string>,warnings:list<string>,price:?int,price_source:string,fallback_used:bool,image_status:string} */
    public function evaluate(CatalogProductData $product, string $channel, ?string $source = null, ?string $fallback = null): array
    {
        $source ??= $this->sourceFor($channel);
        $fallback ??= $this->fallbackFor($channel);
        $price = $this->resolvePrice($product, $source, $fallback);
        $priced = $product->withPrice($price['value']);
        $validation = $this->validator->validate($priced);
        $errors = $validation->errors;
        $warnings = $validation->warnings;
        $imageStatus = $this->imageStatus($product);

        if ($imageStatus === 'invalid') {
            $errors = array_values(array_diff($errors, ['IMAGE_MISSING']));
            $errors[] = 'IMAGE_URL_INVALID';
        } elseif ($imageStatus === 'missing' || $imageStatus === 'not_mirrored') {
            $errors[] = 'IMAGE_MISSING';
        }
        if ($price['value'] === null) {
            $errors[] = 'PRICE_MISSING';
        } elseif ($price['value'] <= 0) {
            $errors[] = 'PRICE_ZERO';
        }
        if ($price['issue'] === 'PRICE_SOURCE_UNAVAILABLE') {
            $errors[] = 'PRICE_SOURCE_UNAVAILABLE';
        }
        if ($price['fallback_used']) {
            $warnings[] = 'PRICE_FALLBACK_USED';
        }

        $errors = array_values(array_unique($errors));
        $warnings = array_values(array_unique($warnings));
        $valid = $errors === [];
        $eligible = $channel === CatalogChannelConnection::GOOGLE_SHEETS ? true : $valid;

        return [
            'eligible' => $eligible,
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings,
            'price' => $price['value'],
            'price_source' => $source,
            'fallback_used' => $price['fallback_used'],
            'image_status' => $imageStatus,
        ];
    }

    public function sourceFor(string $channel): string
    {
        if ($channel === CatalogChannelConnection::GOOGLE_SHEETS) {
            return 'retail_price';
        }

        return (string) $this->settings->forChannel($channel)->price_source;
    }

    public function fallbackFor(string $channel): string
    {
        if ($channel === CatalogChannelConnection::GOOGLE_SHEETS) {
            return 'none';
        }

        return (string) $this->settings->forChannel($channel)->fallback_policy;
    }

    public function imageStatus(CatalogProductData $product): string
    {
        if ($product->imageStatus === 'not_mirrored') {
            return 'not_mirrored';
        }

        return $product->imageUrl === '' ? 'missing' : ($this->validator->isPublicHttpsUrl($product->imageUrl) ? 'has_image' : 'invalid');
    }

    /** @return array{value:?int,fallback_used:bool,issue:?string} */
    private function resolvePrice(CatalogProductData $product, string $source, string $fallback): array
    {
        $price = match (true) {
            $source === 'retail_price' => $product->price,
            $source === 'selected_price' => $product->selectedPrice,
            preg_match('/^price_book:(\d+)$/', $source, $matches) === 1 => data_get($product->priceBooks, (int) $matches[1]),
            default => null,
        };
        $issue = null;
        if ($this->priceValidation->validateSource($source, $this->priceBookId($source)) !== []) {
            $issue = 'PRICE_SOURCE_UNAVAILABLE';
            $price = null;
        } elseif ($price === null) {
            $issue = 'PRICE_BOOK_VALUE_MISSING';
        }
        if ($price !== null && $price >= 0) {
            return ['value' => (int) $price, 'fallback_used' => false, 'issue' => $issue];
        }
        if ($fallback === 'retail_price') {
            return ['value' => $product->price, 'fallback_used' => true, 'issue' => $issue];
        }
        if ($fallback === 'selected_price' && $product->selectedPrice !== null) {
            return ['value' => $product->selectedPrice, 'fallback_used' => true, 'issue' => $issue];
        }

        return ['value' => null, 'fallback_used' => false, 'issue' => $issue];
    }

    private function priceBookId(string $source): ?int
    {
        return preg_match('/^price_book:(\d+)$/', $source, $matches) === 1 ? (int) $matches[1] : null;
    }
}
