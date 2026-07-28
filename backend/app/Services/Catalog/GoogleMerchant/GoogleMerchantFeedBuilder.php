<?php

namespace App\Services\Catalog\GoogleMerchant;

use App\Models\CatalogChannelConnection;
use App\Services\Catalog\Feeds\CatalogCommerceFeedBuilder;

class GoogleMerchantFeedBuilder
{
    public function __construct(
        private readonly CatalogCommerceFeedBuilder $builder,
        private readonly GoogleMerchantXmlRenderer $renderer,
        private readonly GoogleMerchantFeedValidator $validator,
    ) {}

    public function build(bool $dryRun = false, ?int $requestedBy = null): array
    {
        return $this->builder->build(
            CatalogChannelConnection::GOOGLE_MERCHANT,
            (string) config('catalog.google_merchant.artifact'),
            $this->renderer,
            $this->validator,
            $dryRun,
            $requestedBy,
        );
    }

    public function validate(): array
    {
        return $this->builder->validateExisting(
            (string) config('catalog.google_merchant.artifact'),
            $this->validator,
        );
    }
}
