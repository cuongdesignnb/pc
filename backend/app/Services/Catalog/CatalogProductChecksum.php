<?php

namespace App\Services\Catalog;

class CatalogProductChecksum
{
    public function make(array $payload): string
    {
        unset($payload['checksum'], $payload['synced_at']);
        $this->sortRecursively($payload);

        return hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        ));
    }

    private function sortRecursively(array &$value): void
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursively($item);
            }
        }
    }
}
