<?php

namespace App\Services\Catalog\Meta;

use App\Data\Catalog\CatalogProductData;
use App\Exceptions\CatalogChannelException;
use App\Services\Catalog\Feeds\CatalogFeedRenderer;

class MetaCatalogCsvRenderer implements CatalogFeedRenderer
{
    public const HEADERS = [
        'id', 'title', 'description', 'availability', 'condition', 'price', 'link', 'image_link',
        'brand', 'product_type', 'inventory', 'additional_image_link', 'sale_price', 'status',
        'custom_label_0', 'custom_label_1',
    ];

    public function render(iterable $products, string $path): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new CatalogChannelException('FEED_BUILD_FAILED', 'Không thể tạo Meta Catalog feed.');
        }

        try {
            fputcsv($handle, self::HEADERS, ',', '"', '');
            foreach ($products as $product) {
                fputcsv($handle, $this->row($product), ',', '"', '');
            }
        } finally {
            fclose($handle);
        }
    }

    private function row(CatalogProductData $product): array
    {
        return [
            $this->safeText($product->externalId),
            $this->safeText($product->title),
            $this->safeText($product->description ?: $product->title),
            $product->availability,
            $product->condition,
            $product->price.' '.$product->currency,
            $this->safeText($product->productUrl),
            $this->safeText($product->imageUrl),
            $this->safeText($product->brand),
            $this->safeText($product->categoryPath),
            $product->inventory,
            $this->safeText(implode(',', $product->additionalImageUrls)),
            $product->salePrice && $product->salePrice < $product->price
                ? $product->salePrice.' '.$product->currency
                : '',
            'active',
            'KIOT',
            $product->isUnderRepair ? 'UNDER_REPAIR' : '',
        ];
    }

    private function safeText(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
