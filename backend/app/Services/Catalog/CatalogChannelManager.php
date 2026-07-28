<?php

namespace App\Services\Catalog;

use App\Models\CatalogChannelConnection;
use Illuminate\Support\Str;

class CatalogChannelManager
{
    public function connection(string $channel): CatalogChannelConnection
    {
        if (! in_array($channel, CatalogChannelConnection::CHANNELS, true)) {
            throw new \InvalidArgumentException('Unsupported catalog channel.');
        }

        return CatalogChannelConnection::firstOrCreate(
            ['channel' => $channel],
            [
                'status' => 'not_configured',
                'is_enabled' => (bool) config("catalog.{$channel}.enabled", false),
                'configuration_encrypted' => $this->environmentConfiguration($channel),
            ],
        );
    }

    public function isEnabled(string $channel): bool
    {
        $connection = CatalogChannelConnection::where('channel', $channel)->first();

        return $connection
            ? $connection->is_enabled
            : (bool) config("catalog.{$channel}.enabled", false);
    }

    public function rotateFeedToken(CatalogChannelConnection $connection): string
    {
        $token = Str::random(64);
        $configuration = (array) $connection->configuration_encrypted;
        $configuration['feed_token_hash'] = hash('sha256', $token);
        $connection->update([
            'configuration_encrypted' => $configuration,
            'status' => 'configured',
            'last_error_at' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ]);

        return $token;
    }

    public function feedTokenMatches(string $channel, ?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $connection = CatalogChannelConnection::where('channel', $channel)
            ->where('is_enabled', true)
            ->first();
        $expected = (string) data_get($connection?->configuration_encrypted, 'feed_token_hash', '');

        return $expected !== '' && hash_equals($expected, hash('sha256', $token));
    }

    public function feedTokenConfigured(CatalogChannelConnection $connection): bool
    {
        return filled(data_get($connection->configuration_encrypted, 'feed_token_hash'));
    }

    private function environmentConfiguration(string $channel): array
    {
        if ($channel !== CatalogChannelConnection::GOOGLE_SHEETS) {
            return [];
        }

        $credentials = config('catalog.google_sheets.service_account_json');
        if (is_string($credentials) && $credentials !== '') {
            $credentials = json_decode($credentials, true);
        }

        return array_filter([
            'spreadsheet_id' => config('catalog.google_sheets.spreadsheet_id'),
            'worksheet' => config('catalog.google_sheets.worksheet', 'Products'),
            'service_account' => is_array($credentials) ? $credentials : null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
