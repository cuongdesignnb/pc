<?php

namespace App\Services\Catalog\Meta;

use App\Exceptions\CatalogChannelException;
use App\Services\Catalog\Feeds\CatalogFeedRenderer;
use InvalidArgumentException;

class MetaCatalogCsvRenderer implements CatalogFeedRenderer
{
    public const HEADERS = MetaCatalogSchema::HEADERS;

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

    private function row(object $product): array
    {
        if (! $product instanceof MetaCatalogItem) {
            throw new InvalidArgumentException('Meta Catalog renderer received an unsupported item.');
        }

        return array_map(fn (string $value): string => $this->safeText($value), $product->row());
    }

    private function safeText(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
