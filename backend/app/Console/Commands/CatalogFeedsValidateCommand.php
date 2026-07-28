<?php

namespace App\Console\Commands;

use App\Services\Catalog\GoogleMerchant\GoogleMerchantFeedBuilder;
use App\Services\Catalog\Meta\MetaCatalogFeedBuilder;
use Illuminate\Console\Command;

class CatalogFeedsValidateCommand extends Command
{
    protected $signature = 'catalog:feeds:validate';

    protected $description = 'Validate cached Google Merchant and Meta catalog feeds';

    public function handle(GoogleMerchantFeedBuilder $google, MetaCatalogFeedBuilder $meta): int
    {
        $this->line(json_encode([
            'google_merchant' => $google->validate(),
            'meta_catalog' => $meta->validate(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
