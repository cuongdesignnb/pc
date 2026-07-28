<?php

namespace App\Data\Catalog;

final readonly class CatalogValidationResult
{
    public function __construct(
        public bool $valid,
        public array $errors = [],
        public array $warnings = [],
    ) {}

    public function status(CatalogProductData $product): string
    {
        if ($product->isDeleted) {
            return 'DELETED';
        }

        if (! $product->isVisible || ! $product->isActive) {
            return 'HIDDEN';
        }

        return $this->valid ? 'ACTIVE' : 'INVALID';
    }
}
