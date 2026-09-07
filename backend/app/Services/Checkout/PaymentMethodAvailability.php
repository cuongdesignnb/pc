<?php

namespace App\Services\Checkout;

use App\Models\Setting;

class PaymentMethodAvailability
{
    /**
     * @return array<int, array{key: string, label: string, provider: string}>
     */
    public function cartMethods(): array
    {
        $methods = [];
        if ($this->codAvailable()) {
            $methods[] = [
                'key' => 'cod',
                'label' => 'Thanh toán khi nhận hàng',
                'provider' => 'COD',
            ];
        }
        if ($this->sepayAvailable()) {
            $methods[] = [
                'key' => 'sepay',
                'label' => 'Chuyển khoản qua SePay',
                'provider' => $this->sepayDetails()['bank_code'],
            ];
        }

        return $methods;
    }

    /**
     * @return array<int, array{code: string, label: string, description: string, available: bool, disabled_reason: string|null}>
     */
    public function checkoutMethods(): array
    {
        $methods = [];
        if ($this->codAvailable()) {
            $methods[] = [
                'code' => 'cod',
                'label' => 'Thanh toán khi nhận hàng (COD)',
                'description' => 'Thanh toán bằng tiền mặt khi nhận hàng.',
                'available' => true,
                'disabled_reason' => null,
            ];
        }
        if ($this->sepayAvailable()) {
            $methods[] = [
                'code' => 'sepay',
                'label' => 'Chuyển khoản qua SePay',
                'description' => 'Quét mã QR sau khi đơn hàng được ghi nhận.',
                'available' => true,
                'disabled_reason' => null,
            ];
        }

        return $methods;
    }

    public function isAvailable(string $method): bool
    {
        return match ($method) {
            'cod' => $this->codAvailable(),
            'sepay' => $this->sepayAvailable(),
            default => false,
        };
    }

    public function codAvailable(): bool
    {
        return $this->booleanSetting('payment_cod_enabled', true);
    }

    public function sepayAvailable(): bool
    {
        $details = $this->sepayDetails();

        return $details['bank_code'] !== ''
            && $details['bank_account'] !== ''
            && $details['account_name'] !== '';
    }

    /** @return array{bank_code: string, bank_account: string, account_name: string} */
    public function sepayDetails(): array
    {
        $sepay = config('services.sepay', []);

        return [
            'bank_code' => $this->stringSetting('payment_bank_name', (string) ($sepay['bank_code'] ?? '')),
            'bank_account' => $this->stringSetting('payment_bank_account', (string) ($sepay['bank_account'] ?? '')),
            'account_name' => $this->stringSetting('payment_bank_holder', (string) ($sepay['account_name'] ?? '')),
        ];
    }

    private function stringSetting(string $key, string $fallback): string
    {
        $value = Setting::get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : trim($fallback);
    }

    private function booleanSetting(string $key, bool $fallback): bool
    {
        $value = Setting::get($key);
        if ($value === null) {
            return $fallback;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
