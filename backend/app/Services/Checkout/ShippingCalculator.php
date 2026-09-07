<?php

namespace App\Services\Checkout;

use App\Models\Setting;

class ShippingCalculator
{
    /** @return array{code: string, label: string, description: string, available: bool, disabled_reason: string|null, fee: int, eta: string|null} */
    public function quote(int $subtotal, int $quantity, string $method = 'standard'): array
    {
        if ($method !== 'standard') {
            return [
                'code' => $method,
                'label' => '',
                'description' => '',
                'available' => false,
                'disabled_reason' => 'Phương thức giao hàng chưa được hỗ trợ.',
                'fee' => 0,
                'eta' => null,
            ];
        }

        $threshold = $this->integerSetting('shipping_free_threshold', 500000);
        $defaultFee = $this->integerSetting('shipping_default_fee', 30000);
        $fee = $quantity <= 0 ? 0 : ($threshold > 0 && $subtotal >= $threshold ? 0 : $defaultFee);

        return [
            'code' => 'standard',
            'label' => $this->stringSetting('shipping_standard_label', 'Giao hàng tiêu chuẩn'),
            'description' => $this->stringSetting('shipping_standard_description', 'Phí giao hàng được xác nhận theo chính sách hiện tại.'),
            'available' => true,
            'disabled_reason' => null,
            'fee' => $fee,
            'eta' => $this->nullableStringSetting('shipping_standard_eta'),
        ];
    }

    public function freeThreshold(): int
    {
        return $this->integerSetting('shipping_free_threshold', 500000);
    }

    public function defaultFee(): int
    {
        return $this->integerSetting('shipping_default_fee', 30000);
    }

    private function integerSetting(string $key, int $fallback): int
    {
        $value = Setting::get($key);

        return is_numeric($value) ? max(0, (int) $value) : $fallback;
    }

    private function stringSetting(string $key, string $fallback): string
    {
        $value = Setting::get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    private function nullableStringSetting(string $key): ?string
    {
        $value = Setting::get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
