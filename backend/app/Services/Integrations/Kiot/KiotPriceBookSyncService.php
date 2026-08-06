<?php

namespace App\Services\Integrations\Kiot;

use App\Models\CatalogPriceBook;
use Illuminate\Support\Facades\Cache;

class KiotPriceBookSyncService
{
    public function __construct(private readonly KiotClient $client) {}

    public function sync(bool $dryRun = false, ?string $updatedSince = null): array
    {
        $lock = Cache::lock('integrations:kiot:price-books', (int) config('integrations.kiot.sync_lock_seconds', 3600));
        if (! $lock->get()) {
            throw new \RuntimeException('KIOT price-book sync already running.');
        }
        try {
            $items = [];
            $cursor = null;
            do {
                $query = ['limit' => 100, 'include_inactive' => 1];
                if ($cursor) {
                    $query['cursor'] = $cursor;
                }
                if ($updatedSince) {
                    $query['updated_since'] = $updatedSince;
                }
                $response = $this->client->priceBooks($query);
                if (! $response->successful()) {
                    throw new \RuntimeException($response->errorMessage());
                }
                array_push($items, ...$response->data());
                $cursor = $response->meta()['next_cursor'] ?? null;
            } while ($cursor);

            $report = ['PRICE_BOOKS_REMOTE' => count($items), 'PRICE_BOOKS_CREATE' => 0, 'PRICE_BOOKS_UPDATE' => 0, 'PRICE_BOOKS_UNCHANGED' => 0, 'ERRORS' => 0, 'WARNINGS' => 0];
            foreach ($items as $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id <= 0 || trim((string) ($item['name'] ?? '')) === '') {
                    $report['ERRORS']++;

                    continue;
                }
                $attrs = [
                    'provider' => 'kiot',
                    'remote_price_book_id' => $id,
                    'name' => trim((string) $item['name']),
                    'code' => $item['code'] ?? null,
                    'is_active' => (bool) ($item['is_active'] ?? false) && ($item['sync_status'] ?? 'active') === 'active',
                    'currency' => $item['currency'] ?? 'VND',
                    'effective_from' => $item['effective_from'] ?? $item['start_date'] ?? null,
                    'effective_to' => $item['effective_to'] ?? $item['end_date'] ?? null,
                    'provider_updated_at' => $item['updated_at'] ?? null,
                    'synced_at' => now(),
                    'checksum' => hash('sha256', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                ];
                $existing = CatalogPriceBook::query()->where('provider', 'kiot')->where('remote_price_book_id', $id)->first();
                if ($existing === null) {
                    $report['PRICE_BOOKS_CREATE']++;
                } elseif ((string) $existing->checksum === $attrs['checksum']) {
                    $report['PRICE_BOOKS_UNCHANGED']++;
                } else {
                    $report['PRICE_BOOKS_UPDATE']++;
                }
                if (! $dryRun) {
                    CatalogPriceBook::updateOrCreate(
                        ['provider' => 'kiot', 'remote_price_book_id' => $id],
                        $attrs,
                    );
                }
            }

            return $report;
        } finally {
            $lock->release();
        }
    }
}
