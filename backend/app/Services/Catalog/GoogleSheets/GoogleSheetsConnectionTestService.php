<?php

namespace App\Services\Catalog\GoogleSheets;

use App\Models\CatalogChannelConnection;
use App\Services\Catalog\CatalogChannelManager;
use Throwable;

class GoogleSheetsConnectionTestService
{
    public function __construct(
        private readonly GoogleSheetsClient $client,
        private readonly CatalogChannelManager $channels,
    ) {}

    public function test(): array
    {
        $connection = $this->channels->connection(CatalogChannelConnection::GOOGLE_SHEETS);

        try {
            $result = $this->client->test((array) $connection->configuration_encrypted);
            $connection->update([
                'status' => 'connected',
                'last_tested_at' => now(),
                'last_success_at' => now(),
                'last_error_at' => null,
                'last_error_code' => null,
                'last_error_message' => null,
            ]);

            return ['success' => true] + $result;
        } catch (Throwable $exception) {
            $code = $exception instanceof \App\Exceptions\CatalogChannelException
                ? $exception->errorCode
                : 'GOOGLE_AUTH_FAILED';
            $connection->update([
                'status' => 'error',
                'last_tested_at' => now(),
                'last_error_at' => now(),
                'last_error_code' => $code,
                'last_error_message' => 'Google Sheets connection test failed.',
            ]);
            throw $exception;
        }
    }
}
