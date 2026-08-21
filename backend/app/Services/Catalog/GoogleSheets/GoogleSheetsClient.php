<?php

namespace App\Services\Catalog\GoogleSheets;

use App\Exceptions\CatalogChannelException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleSheetsClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const API_BASE = 'https://sheets.googleapis.com/v4/spreadsheets';

    public function test(array $configuration): array
    {
        $response = $this->request($configuration)->get($this->spreadsheetUrl($configuration), [
            'fields' => 'spreadsheetId,sheets.properties.title',
        ]);
        $this->assertSuccessful($response, 'GOOGLE_SHEET_NOT_FOUND');
        $worksheet = (string) ($configuration['worksheet'] ?? 'Products');
        $titles = collect($response->json('sheets', []))->pluck('properties.title');
        if (! $titles->containsStrict($worksheet)) {
            throw new CatalogChannelException('GOOGLE_WORKSHEET_NOT_FOUND', 'Không tìm thấy worksheet đã cấu hình.');
        }

        return ['spreadsheet_id' => (string) $response->json('spreadsheetId'), 'worksheet' => $worksheet];
    }

    public function readRows(array $configuration, int $columnCount): array
    {
        $range = $this->quotedWorksheet($configuration).'!A:'.$this->columnName($columnCount);
        $response = $this->request($configuration)->get(
            $this->spreadsheetUrl($configuration).'/values:batchGet',
            ['majorDimension' => 'ROWS', 'ranges' => $range],
        );
        $this->assertSuccessful($response, 'GOOGLE_INVALID_RESPONSE');

        return (array) $response->json('valueRanges.0.values', []);
    }

    public function writeRows(array $configuration, array $ranges, int $columnCount): void
    {
        if ($ranges === []) {
            return;
        }

        $this->ensureColumnCapacity($configuration, $columnCount);

        foreach (array_chunk($ranges, max(1, (int) config('catalog.sync_chunk_size', 250))) as $chunk) {
            $response = $this->request($configuration)->post(
                $this->spreadsheetUrl($configuration).'/values:batchUpdate',
                ['valueInputOption' => 'RAW', 'data' => $chunk],
            );
            $this->assertSuccessful($response, 'GOOGLE_WRITE_FAILED');
        }
    }

    private function ensureColumnCapacity(array $configuration, int $columnCount): void
    {
        $response = $this->request($configuration)->get($this->spreadsheetUrl($configuration), [
            'fields' => 'sheets.properties',
        ]);
        $this->assertSuccessful($response, 'GOOGLE_WRITE_FAILED');

        $worksheet = (string) ($configuration['worksheet'] ?? 'Products');
        $sheet = collect($response->json('sheets', []))->first(
            fn (array $candidate): bool => data_get($candidate, 'properties.title') === $worksheet,
        );
        if (! is_array($sheet)) {
            throw new CatalogChannelException('GOOGLE_WORKSHEET_NOT_FOUND', 'Không tìm thấy worksheet đã cấu hình.');
        }

        $sheetId = (int) data_get($sheet, 'properties.sheetId', -1);
        $currentColumnCount = (int) data_get($sheet, 'properties.gridProperties.columnCount', 0);
        if ($currentColumnCount >= $columnCount) {
            return;
        }
        if ($sheetId < 0) {
            throw new CatalogChannelException('GOOGLE_INVALID_RESPONSE', 'Google Sheets worksheet metadata không hợp lệ.');
        }

        $response = $this->request($configuration)->post(
            $this->spreadsheetUrl($configuration).':batchUpdate',
            [
                'requests' => [[
                    'appendDimension' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'length' => $columnCount - $currentColumnCount,
                    ],
                ]],
            ],
        );
        $this->assertSuccessful($response, 'GOOGLE_WRITE_FAILED');
    }

    public function rowRange(array $configuration, int $row, int $columnCount): string
    {
        return $this->quotedWorksheet($configuration)."!A{$row}:{$this->columnName($columnCount)}{$row}";
    }

    private function request(array $configuration): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($this->accessToken($configuration))
            ->connectTimeout((int) config('catalog.google_sheets.connect_timeout_seconds', 5))
            ->timeout((int) config('catalog.google_sheets.request_timeout_seconds', 20))
            ->retry(
                (int) config('catalog.google_sheets.max_attempts', 4),
                fn (int $attempt): int => min(8000, 250 * (2 ** ($attempt - 1))),
                fn ($exception, PendingRequest $request): bool => true,
                throw: false,
            );
    }

    private function accessToken(array $configuration): string
    {
        $credentials = $this->credentials($configuration);
        $cacheKey = 'catalog:google-sheets:token:'.hash('sha256', $credentials['client_email'].$credentials['private_key']);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials): string {
            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/spreadsheets',
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $unsigned = $header.'.'.$claims;
            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new CatalogChannelException('GOOGLE_AUTH_FAILED', 'Không thể ký yêu cầu xác thực Google.');
            }

            $response = Http::asForm()
                ->connectTimeout((int) config('catalog.google_sheets.connect_timeout_seconds', 5))
                ->timeout((int) config('catalog.google_sheets.request_timeout_seconds', 20))
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $unsigned.'.'.$this->base64Url($signature),
                ]);

            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new CatalogChannelException('GOOGLE_AUTH_FAILED', 'Google từ chối thông tin xác thực.');
            }

            return $response->json('access_token');
        });
    }

    private function credentials(array $configuration): array
    {
        $credentials = $configuration['service_account'] ?? null;
        if (! is_array($credentials)
            || ! filled($credentials['client_email'] ?? null)
            || ! filled($credentials['private_key'] ?? null)) {
            throw new CatalogChannelException('GOOGLE_CREDENTIALS_MISSING', 'Chưa cấu hình Google Service Account.');
        }

        return $credentials;
    }

    private function spreadsheetUrl(array $configuration): string
    {
        $id = (string) ($configuration['spreadsheet_id'] ?? '');
        if (preg_match('/^[A-Za-z0-9_-]{10,200}$/', $id) !== 1) {
            throw new CatalogChannelException('GOOGLE_SHEET_NOT_FOUND', 'Spreadsheet ID không hợp lệ.');
        }

        return self::API_BASE.'/'.rawurlencode($id);
    }

    private function quotedWorksheet(array $configuration): string
    {
        $worksheet = (string) ($configuration['worksheet'] ?? 'Products');
        if ($worksheet === '' || mb_strlen($worksheet) > 100 || preg_match('/[\x00-\x1F]/u', $worksheet)) {
            throw new CatalogChannelException('GOOGLE_WORKSHEET_NOT_FOUND', 'Worksheet không hợp lệ.');
        }

        return "'".str_replace("'", "''", $worksheet)."'";
    }

    private function columnName(int $columnCount): string
    {
        if ($columnCount < 1) {
            throw new CatalogChannelException('GOOGLE_INVALID_RESPONSE', 'Google Sheets range không hợp lệ.');
        }

        $name = '';
        while ($columnCount > 0) {
            $columnCount--;
            $name = chr(65 + ($columnCount % 26)).$name;
            $columnCount = intdiv($columnCount, 26);
        }

        return $name;
    }

    private function assertSuccessful(Response $response, string $fallbackCode): void
    {
        if ($response->successful()) {
            return;
        }

        $code = $response->status() === 429 ? 'GOOGLE_RATE_LIMITED' : $fallbackCode;
        throw new CatalogChannelException($code, 'Google Sheets API không hoàn thành yêu cầu.');
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
