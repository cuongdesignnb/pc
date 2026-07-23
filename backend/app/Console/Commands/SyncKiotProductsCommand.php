<?php

namespace App\Console\Commands;

use App\Services\Integrations\Kiot\KiotProductSyncService;
use Illuminate\Console\Command;

class SyncKiotProductsCommand extends Command
{
    protected $signature = 'kiot:sync-products {--dry-run} {--apply} {--full} {--sku=}';

    protected $description = 'Đối chiếu hoặc đồng bộ sản phẩm từ KIOT';

    public function handle(KiotProductSyncService $service): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Chỉ chọn một trong --dry-run hoặc --apply.');

            return self::INVALID;
        }
        $report = $service->sync(! $this->option('apply'), (bool) $this->option('full'), $this->option('sku') ?: null);
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
