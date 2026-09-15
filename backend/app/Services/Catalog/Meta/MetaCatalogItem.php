<?php

namespace App\Services\Catalog\Meta;

final readonly class MetaCatalogItem
{
    /** @var array<string, scalar|null> */
    public array $values;

    /**
     * @param  array<string, scalar|null>  $values
     */
    public function __construct(
        public int $productId,
        array $values,
        public bool $approvedUnbranded = false,
        public bool $variant = false,
        public bool $brandDerivedFromTitle = false,
        public bool $descriptionDerivedFromFacts = false,
    ) {
        $normalized = [];
        foreach (MetaCatalogSchema::HEADERS as $header) {
            $normalized[$header] = $values[$header] ?? '';
        }

        $this->values = $normalized;
    }

    public function value(string $field): string
    {
        return (string) ($this->values[$field] ?? '');
    }

    /** @return list<string> */
    public function row(): array
    {
        return array_map(
            fn (string $header): string => $this->value($header),
            MetaCatalogSchema::HEADERS,
        );
    }

    public function checksum(): string
    {
        return hash('sha256', json_encode(
            $this->values,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }
}
