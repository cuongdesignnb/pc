<?php

namespace App\Services\Catalog\Feeds;

interface CatalogFeedRenderer
{
    public function render(iterable $products, string $path): void;
}
