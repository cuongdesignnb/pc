<?php

namespace App\Data\Catalog;

use Carbon\CarbonImmutable;

final readonly class CatalogProductData
{
    public function __construct(
        public int $id,
        public string $externalId,
        public string $sku,
        public string $title,
        public string $description,
        public ?int $categoryId,
        public string $categoryName,
        public string $categoryPath,
        public bool $categoryVisible,
        public string $brand,
        public string $condition,
        public string $availability,
        public int $inventory,
        public int $price,
        public string $currency,
        public ?int $salePrice,
        public string $productUrl,
        public string $imageUrl,
        public array $additionalImageUrls,
        public bool $isActive,
        public bool $isVisible,
        public bool $isUnderRepair,
        public bool $isDeleted,
        public CarbonImmutable $updatedAt,
        public string $checksum,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->externalId,
            'sku' => $this->sku,
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $this->categoryId,
            'category_name' => $this->categoryName,
            'category_path' => $this->categoryPath,
            'category_visible' => $this->categoryVisible,
            'brand' => $this->brand,
            'condition' => $this->condition,
            'availability' => $this->availability,
            'inventory' => $this->inventory,
            'price' => $this->price,
            'currency' => $this->currency,
            'sale_price' => $this->salePrice,
            'product_url' => $this->productUrl,
            'image_url' => $this->imageUrl,
            'additional_image_urls' => $this->additionalImageUrls,
            'is_active' => $this->isActive,
            'is_visible' => $this->isVisible,
            'is_under_repair' => $this->isUnderRepair,
            'is_deleted' => $this->isDeleted,
            'updated_at' => $this->updatedAt->toIso8601String(),
            'checksum' => $this->checksum,
        ];
    }
}
