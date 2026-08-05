<?php

namespace App\Console\Commands;

use App\Services\Integrations\Kiot\KiotPriceBookSyncService;
use Illuminate\Console\Command;

class KiotPriceBooksSyncCommand extends Command
{
    protected $signature = 'kiot:price-books:sync {--dry-run} {--updated-since=}';

    protected $description = 'Synchronize KIOT price-book definitions without selecting a production book';

    public function handle(KiotPriceBookSyncService $service): int
    {
        $result = $service->sync((bool) $this->option('dry-run'), $this->option('updated-since'));
        $this->table(['Metric', 'Value'], collect($result)->map(fn ($value, $key) => [$key, is_array($value) ? json_encode($value) : $value]));

        return self::SUCCESS;
    }
}
