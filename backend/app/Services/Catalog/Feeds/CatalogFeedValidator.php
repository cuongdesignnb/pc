<?php

namespace App\Services\Catalog\Feeds;

interface CatalogFeedValidator
{
    public function validate(string $path): array;
}
