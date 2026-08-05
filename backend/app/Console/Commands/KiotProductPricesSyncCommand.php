<?php

namespace App\Console\Commands;

use App\Services\Integrations\Kiot\KiotProductPriceSyncService;
use Illuminate\Console\Command;

class KiotProductPricesSyncCommand extends Command
{
    protected $signature = 'kiot:product-prices:sync {--dry-run} {--sku=}';

    protected $description = 'Synchronize KIOT retail, selected and per-price-book values';

    public function handle(KiotProductPriceSyncService $service): int
    {
        $result = $service->sync((bool) $this->option('dry-run'), $this->option('sku'));
        $this->table(['Metric', 'Value'], collect($result)->map(fn ($value, $key) => [$key, is_array($value) ? json_encode($value) : $value]));

        return self::SUCCESS;
    }
}
