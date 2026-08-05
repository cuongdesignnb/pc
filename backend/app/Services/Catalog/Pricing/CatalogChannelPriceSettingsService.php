<?php

namespace App\Services\Catalog\Pricing;

use App\Models\CatalogChannelPriceSetting;
use Illuminate\Validation\ValidationException;

class CatalogChannelPriceSettingsService
{
    public function __construct(private readonly CatalogPriceValidationService $validator) {}

    public function all(): array
    {
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

        return $settings;
    }

    public function update(string $channel, string $priceSource, string $fallbackPolicy, ?int $configuredBy = null): CatalogChannelPriceSetting
    {
        abort_unless(in_array($channel, CatalogChannelPriceSetting::CHANNELS, true), 404);
        if (! in_array($fallbackPolicy, CatalogChannelPriceSetting::FALLBACK_POLICIES, true)) {
            throw ValidationException::withMessages(['fallback_policy' => 'Fallback policy không hợp lệ.']);
        }

        $priceBookId = null;
        if (preg_match('/^price_book:(\d+)$/', $priceSource, $matches) === 1) {
            $priceBookId = (int) $matches[1];
        }
        $errors = $this->validator->validateSource($priceSource, $priceBookId);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return CatalogChannelPriceSetting::updateOrCreate(
            ['channel' => $channel],
            [
                'price_source' => $priceSource,
                'price_book_id' => $priceBookId,
                'fallback_policy' => $fallbackPolicy,
                'configured_by' => $configuredBy,
                'configured_at' => now(),
            ],
        );
    }
}
