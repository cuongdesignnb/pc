<?php

namespace App\Services\Integrations\Kiot;

use App\Models\CatalogPriceBook;
use App\Models\CatalogProductPrice;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class KiotProductPriceSyncService
{
    public function __construct(private readonly KiotClient $client) {}

    public function sync(bool $dryRun = false, ?string $sku = null): array
    {
        $lock = Cache::lock('integrations:kiot:product-prices', (int) config('integrations.kiot.sync_lock_seconds', 3600));
        if (! $lock->get()) {
            throw new \RuntimeException('KIOT product-price sync already running.');
        }
        try {
            $remote = [];
            $cursor = null;
            do {
                $query = ['limit' => 100, 'include_inactive' => 1];
                if ($cursor) {
                    $query['cursor'] = $cursor;
                }
                if ($sku !== null) {
                    $response = $this->client->product(trim($sku));
                    $remote = $response->successful() ? [$response->data()] : [];
                    $cursor = null;
                    break;
                }
                $response = $this->client->products($query);
                if (! $response->successful()) {
                    throw new \RuntimeException($response->errorMessage());
                }
                array_push($remote, ...$response->data());
                $cursor = $response->meta()['next_cursor'] ?? null;
            } while ($cursor);

            $books = CatalogPriceBook::query()->where('provider', 'kiot')->get()->keyBy('remote_price_book_id');
            $report = ['PRODUCT_PRICE_ROWS_REMOTE' => 0, 'PRODUCT_PRICE_ROWS_CREATE' => 0, 'PRODUCT_PRICE_ROWS_UPDATE' => 0, 'PRODUCT_PRICE_ROWS_UNCHANGED' => 0, 'ERRORS' => 0, 'WARNINGS' => 0];
            foreach ($remote as $item) {
                $product = Product::query()->where('provider', 'kiot')->where(function ($query) use ($item) {
                    $query->where('remote_product_id', (int) ($item['id'] ?? 0))->orWhere('sku', trim((string) ($item['sku'] ?? '')));
                })->first();
                if (! $product) {
                    $report['WARNINGS']++;

                    continue;
                }
                $pricing = (array) ($item['pricing'] ?? []);
                $rows = [];
                if (array_key_exists('retail_price', $pricing)) {
                    $rows[] = ['price_source' => 'retail_price', 'price_book_id' => null, 'price' => max(0, (int) $pricing['retail_price'])];
                }
                if (array_key_exists('selected_price', $pricing)) {
                    $rows[] = ['price_source' => 'selected_price', 'price_book_id' => null, 'price' => max(0, (int) $pricing['selected_price'])];
                }
                $bookValues = $pricing['price_books'] ?? $pricing['all_price_books'] ?? [];
                if (is_array($bookValues)) {
                    foreach ($bookValues as $key => $value) {
                        $bookId = is_array($value) ? (int) ($value['id'] ?? $value['price_book_id'] ?? 0) : (int) $key;
                        $price = is_array($value) ? ($value['price'] ?? null) : $value;
                        if ($bookId <= 0 || ! is_numeric($price)) {
                            continue;
                        }
                        $book = $books->get($bookId);
                        if (! $book) {
                            $report['WARNINGS']++;

                            continue;
                        }
                        $rows[] = ['price_source' => 'price_book', 'price_book_id' => $book->id, 'price' => max(0, (int) $price)];
                    }
                }
                if ($rows === []) {
                    $report['WARNINGS']++;

                    continue;
                }
                foreach ($rows as $row) {
                    $report['PRODUCT_PRICE_ROWS_REMOTE']++;
                    $attributes = $row + [
                        'currency' => 'VND',
                        'provider_updated_at' => $item['updated_at'] ?? null,
                        'synced_at' => now(),
                        'checksum' => hash('sha256', json_encode($row + ['product_id' => $product->id], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    ];
                    $existing = CatalogProductPrice::query()->where('product_id', $product->id)->where('price_source', $row['price_source'])->where('price_book_id', $row['price_book_id'])->first();
                    if ($existing === null) {
                        $report['PRODUCT_PRICE_ROWS_CREATE']++;
                    } elseif ((string) $existing->checksum === $attributes['checksum']) {
                        $report['PRODUCT_PRICE_ROWS_UNCHANGED']++;
                    } else {
                        $report['PRODUCT_PRICE_ROWS_UPDATE']++;
                    }
                    if (! $dryRun) {
                        CatalogProductPrice::updateOrCreate(
                            ['product_id' => $product->id, 'price_source' => $row['price_source'], 'price_book_id' => $row['price_book_id']],
                            $attributes,
                        );
                    }
                }
            }

            return $report;
        } finally {
            $lock->release();
        }
    }
}
