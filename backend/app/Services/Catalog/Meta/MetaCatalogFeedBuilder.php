<?php

namespace App\Services\Catalog\Meta;

use App\Models\CatalogChannelConnection;
use App\Services\Catalog\Feeds\CatalogCommerceFeedBuilder;

class MetaCatalogFeedBuilder
{
    public function __construct(
        private readonly CatalogCommerceFeedBuilder $builder,
        private readonly MetaCatalogCsvRenderer $renderer,
        private readonly MetaCatalogFeedValidator $validator,
    ) {}

    public function build(bool $dryRun = false, ?int $requestedBy = null): array
    {
        return $this->builder->build(
            CatalogChannelConnection::META_CATALOG,
            (string) config('catalog.meta_catalog.artifact'),
            $this->renderer,
            $this->validator,
            $dryRun,
            $requestedBy,
        );
    }

    public function validate(): array
    {
        return $this->builder->validateExisting(
            (string) config('catalog.meta_catalog.artifact'),
            $this->validator,
        );
    }
}
