<?php

namespace App\Services\Integrations\Kiot;

final readonly class KiotRuntimeConfiguration
{
    public function __construct(
        public string $source,
        public ?int $databaseConnectionId,
        public ?string $baseUrl,
        public ?string $clientId,
        public ?string $secret,
        public string $apiVersion,
        public bool $enabled,
        public bool $productSyncEnabled,
        public bool $orderSyncEnabled,
        public int $connectTimeoutSeconds,
        public int $requestTimeoutSeconds,
        public int $productSyncLimit,
        public int $productSyncOverlapSeconds,
        public int $productStaleAfterMinutes,
        public int $outboxMaxAttempts,
        public int $outboxRetryBaseSeconds,
        public bool $configured,
        public bool $connected,
        public array $capabilities,
    ) {}
}
