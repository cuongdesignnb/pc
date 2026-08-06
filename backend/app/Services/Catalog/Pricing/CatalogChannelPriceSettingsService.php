<?php

namespace App\Services\Catalog\Pricing;

use App\Models\CatalogChannelConnection;
use App\Models\CatalogChannelPriceSetting;
use App\Models\CatalogGoogleSheetPriceColumn;
use App\Services\Catalog\CatalogChannelManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogChannelPriceSettingsService
{
    public const DEFAULT_GOOGLE_SHEETS_SOURCES = ['retail_price'];

    private ?array $cachedSettings = null;

    public function __construct(
        private readonly CatalogPriceValidationService $validator,
        private readonly CatalogChannelManager $channels,
    ) {}

    public function all(): array
    {
        if ($this->cachedSettings !== null) {
            return $this->cachedSettings;
        }

        $existing = CatalogChannelPriceSetting::query()->get()->keyBy('channel');
        $settings = [];
        foreach (CatalogChannelPriceSetting::CHANNELS as $channel) {
            $settings[$channel] = $existing->get($channel) ?? new CatalogChannelPriceSetting([
                'channel' => $channel,
                'price_source' => 'retail_price',
                'fallback_policy' => 'none',
                'is_enabled' => $channel === CatalogChannelPriceSetting::WEBSITE,
            ]);
        }

        return $this->cachedSettings = $settings;
    }

    public function forChannel(string $channel): CatalogChannelPriceSetting
    {
        return $this->all()[$channel] ?? abort(404);
    }

    public function update(
        string $channel,
        string $priceSource,
        string $fallbackPolicy,
        ?int $configuredBy = null,
    ): CatalogChannelPriceSetting {
        abort_unless(in_array($channel, CatalogChannelPriceSetting::CHANNELS, true), 404);
        if ($channel === CatalogChannelPriceSetting::GOOGLE_SHEETS) {
            throw ValidationException::withMessages([
                'price_source' => 'Google Sheets sử dụng nhiều nguồn giá; hãy lưu danh sách cột riêng.',
            ]);
        }
        $this->validateFallback($fallbackPolicy);
        $priceBookId = $this->priceBookId($priceSource);
        $errors = $this->validator->validateSource($priceSource, $priceBookId);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $updated = CatalogChannelPriceSetting::updateOrCreate(
            ['channel' => $channel],
            [
                'price_source' => $priceSource,
                'price_book_id' => $priceBookId,
                'fallback_policy' => $fallbackPolicy,
                'configured_by' => $configuredBy,
                'configured_at' => now(),
            ],
        );
        $this->cachedSettings = null;

        return $updated;
    }

    /** @return Collection<int, CatalogGoogleSheetPriceColumn> */
    public function googleSheetsColumns(): Collection
    {
        $connectionId = CatalogChannelConnection::query()
            ->where('channel', CatalogChannelConnection::GOOGLE_SHEETS)
            ->value('id');

        if ($connectionId === null) {
            return collect();
        }

        return CatalogGoogleSheetPriceColumn::query()
            ->with('priceBook:id,name,code,is_active,remote_price_book_id')
            ->where('connection_id', $connectionId)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** @return list<string> */
    public function googleSheetsSources(): array
    {
        $columns = $this->googleSheetsColumns();

        return $columns->isEmpty()
            ? self::DEFAULT_GOOGLE_SHEETS_SOURCES
            : $columns->pluck('price_source')->values()->all();
    }

    /** @param list<string> $sources */
    public function updateGoogleSheetsSources(array $sources): Collection
    {
        $sources = array_values(array_map('strval', $sources));
        if (count($sources) !== count(array_unique($sources))) {
            throw ValidationException::withMessages([
                'sources' => 'Google Sheets không được chọn trùng nguồn giá.',
            ]);
        }
        if ($sources === []) {
            throw ValidationException::withMessages([
                'sources' => 'Phải chọn ít nhất một nguồn giá cho Google Sheets.',
            ]);
        }

        $normalized = [];
        foreach ($sources as $sortOrder => $source) {
            $priceBookId = $this->priceBookId($source);
            $errors = $this->validator->validateSource($source, $priceBookId);
            if ($errors !== []) {
                throw ValidationException::withMessages([
                    'sources' => $errors['price_source'] ?? 'Nguồn giá không hợp lệ.',
                ]);
            }
            $normalized[] = [
                'price_source' => $source,
                'price_book_id' => $priceBookId,
                'column_key' => $this->columnKey($source),
                'column_label' => $this->columnLabel($source, $priceBookId),
                'sort_order' => $sortOrder,
                'is_enabled' => true,
            ];
        }

        $connection = $this->channels->connection(CatalogChannelConnection::GOOGLE_SHEETS);
        DB::transaction(function () use ($connection, $normalized): void {
            $connection->googleSheetPriceColumns()->delete();
            foreach ($normalized as $column) {
                $connection->googleSheetPriceColumns()->create($column);
            }
        });

        return $this->googleSheetsColumns();
    }

    public function validateFallback(string $fallbackPolicy): void
    {
        if (! in_array($fallbackPolicy, CatalogChannelPriceSetting::FALLBACK_POLICIES, true)) {
            throw ValidationException::withMessages([
                'fallback_policy' => 'Fallback policy không hợp lệ.',
            ]);
        }
    }

    private function priceBookId(string $priceSource): ?int
    {
        if (preg_match('/^price_book:(\d+)$/', $priceSource, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function columnKey(string $priceSource): string
    {
        return $priceSource === 'retail_price'
            ? 'retail_price'
            : ($priceSource === 'selected_price' ? 'selected_price' : 'price_book_'.(string) $this->priceBookId($priceSource));
    }

    private function columnLabel(string $priceSource, ?int $priceBookId): string
    {
        return match ($priceSource) {
            'retail_price' => 'Retail price',
            'selected_price' => 'Selected price',
            default => 'Price book: '.((string) ($priceBookId ?? 'unknown')),
        };
    }
}
