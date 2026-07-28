<?php

namespace App\Console\Commands;

use App\Services\Catalog\GoogleMerchant\GoogleMerchantFeedBuilder;
use App\Services\Catalog\Meta\MetaCatalogFeedBuilder;
use Illuminate\Console\Command;

class CatalogFeedsBuildCommand extends Command
{
    protected $signature = 'catalog:feeds:build {--dry-run}';

    protected $description = 'Build Google Merchant and Meta catalog feed artifacts';

    public function handle(GoogleMerchantFeedBuilder $google, MetaCatalogFeedBuilder $meta): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->line(json_encode([
            'google_merchant' => $google->build($dryRun),
            'meta_catalog' => $meta->build($dryRun),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
