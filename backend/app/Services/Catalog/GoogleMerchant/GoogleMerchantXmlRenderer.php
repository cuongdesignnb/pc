<?php

namespace App\Services\Catalog\GoogleMerchant;

use App\Data\Catalog\CatalogProductData;
use App\Exceptions\CatalogChannelException;
use App\Services\Catalog\Feeds\CatalogFeedRenderer;

class GoogleMerchantXmlRenderer implements CatalogFeedRenderer
{
    public function render(iterable $products, string $path): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new CatalogChannelException('FEED_BUILD_FAILED', 'Không thể tạo Google Merchant feed.');
        }

        try {
            fwrite($handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
            fwrite($handle, "<rss version=\"2.0\" xmlns:g=\"http://base.google.com/ns/1.0\"><channel>\n");
            fwrite($handle, '<title>'.self::xml((string) config('app.name'))."</title>\n");
            fwrite($handle, '<link>'.self::xml((string) config('catalog.storefront_url'))."</link>\n");
            fwrite($handle, "<description>Product catalog</description>\n");

            foreach ($products as $product) {
                $this->writeProduct($handle, $product);
            }

            fwrite($handle, "</channel></rss>\n");
        } finally {
            fclose($handle);
        }
    }

    private function writeProduct($handle, CatalogProductData $product): void
    {
        fwrite($handle, "<item>\n");
        $this->element($handle, 'g:id', $product->externalId);
        $this->element($handle, 'g:title', $product->title);
        $this->element($handle, 'g:description', $product->description ?: $product->title);
        $this->element($handle, 'g:link', $product->productUrl);
        $this->element($handle, 'g:image_link', $product->imageUrl);
        foreach (array_slice($product->additionalImageUrls, 0, 10) as $image) {
            $this->element($handle, 'g:additional_image_link', $image);
        }
        $this->element($handle, 'g:availability', $product->availability);
        $this->element($handle, 'g:price', $product->price.' '.$product->currency);
        if ($product->salePrice !== null && $product->salePrice > 0 && $product->salePrice < $product->price) {
            $this->element($handle, 'g:sale_price', $product->salePrice.' '.$product->currency);
        }
        $this->element($handle, 'g:condition', $product->condition);
        if ($product->brand !== '') {
            $this->element($handle, 'g:brand', $product->brand);
            $this->element($handle, 'g:mpn', $product->sku);
        } else {
            $this->element($handle, 'g:identifier_exists', 'no');
        }
        $this->element($handle, 'g:product_type', $product->categoryPath);
        $this->element($handle, 'g:quantity', (string) $product->inventory);
        fwrite($handle, "</item>\n");
    }

    private function element($handle, string $name, string $value): void
    {
        fwrite($handle, '<'.$name.'>'.self::xml($value).'</'.$name.">\n");
    }

    private static function xml(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
