<?php

namespace App\Services\Catalog;

use App\Data\Catalog\CatalogProductData;
use App\Data\Catalog\CatalogValidationResult;

class CatalogProductValidator
{
    public function validate(CatalogProductData $product): CatalogValidationResult
    {
        $errors = [];
        $warnings = [];

        if ($product->isDeleted) {
            $errors[] = 'PRODUCT_DELETED';
        }
        if (! $product->isActive) {
            $errors[] = 'PRODUCT_INACTIVE';
        }
        if (! $product->categoryVisible) {
            $errors[] = 'CATEGORY_HIDDEN';
        }
        if (! $product->isVisible && $product->categoryVisible) {
            $errors[] = 'PRODUCT_HIDDEN';
        }
        if ($product->sku === '') {
            $errors[] = 'SKU_MISSING';
        }
        if ($product->title === '') {
            $errors[] = 'TITLE_MISSING';
        }
        if ($product->price <= 0) {
            $errors[] = 'PRICE_MISSING';
        }
        if (! $this->isPublicHttpsUrl($product->productUrl)) {
            $errors[] = 'PRODUCT_URL_MISSING';
        }
        if ($product->imageUrl === '' || ! $this->isPublicHttpsUrl($product->imageUrl)) {
            $errors[] = 'IMAGE_MISSING';
        }
        if ($product->isUnderRepair) {
            $warnings[] = 'UNDER_REPAIR';
        } elseif ($product->availability === 'out_of_stock') {
            $warnings[] = 'OUT_OF_STOCK';
        }

        return new CatalogValidationResult($errors === [], array_values(array_unique($errors)), $warnings);
    }

    public function isPublicHttpsUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return str_contains($host, '.');
    }
}
