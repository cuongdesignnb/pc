<?php

namespace App\Services\Catalog\Feeds;

use App\Data\Catalog\CatalogProductData;
use App\Data\Catalog\CatalogValidationResult;

interface CatalogFeedItemStrategy
{
    /** @return list<object> */
    public function items(CatalogProductData $product): array;

    public function validate(object $item, CatalogProductData $source): CatalogValidationResult;

    public function externalId(object $item): string;

    /** @param list<object> $items */
    public function checksum(CatalogProductData $source, array $items): string;

    public function duplicateIdErrorCode(): string;

    /**
     * @param  list<object>  $items
     * @return array<string, int>
     */
    public function summaryCounters(CatalogProductData $source, array $items): array;
}
