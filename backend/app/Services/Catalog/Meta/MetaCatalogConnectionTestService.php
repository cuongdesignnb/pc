<?php

namespace App\Services\Catalog\Meta;

use App\Models\CatalogChannelConnection;
use App\Services\Catalog\CatalogChannelManager;

class MetaCatalogConnectionTestService
{
    public function __construct(private readonly CatalogChannelManager $channels) {}

    public function test(): array
    {
        $connection = $this->channels->connection(CatalogChannelConnection::META_CATALOG);
        $configuration = (array) $connection->configuration_encrypted;
        $configured = filled($configuration['catalog_id'] ?? null) && filled($configuration['access_token'] ?? null);

        return [
            'configured' => $configured,
            'enabled' => (bool) $connection->is_enabled,
            'test_mode' => (bool) config('catalog.meta_catalog.test_mode', true),
            'remote_submit' => false,
        ];
    }
}
