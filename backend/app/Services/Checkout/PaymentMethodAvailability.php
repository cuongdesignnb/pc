<?php

namespace App\Services\Checkout;

use App\Models\Setting;
use App\Services\Payments\DepositPolicy;

class PaymentMethodAvailability
{
    public const DEPOSIT_REQUIRED_MESSAGE = 'Đơn giao đến tỉnh/thành này cần đặt cọc trước.';

    public const COD_PROVINCE_MESSAGE = 'Đơn giao đến tỉnh/thành này cần chuyển khoản đặt cọc hoặc thanh toán trước, không áp dụng COD.';

    public function __construct(private readonly DepositPolicy $deposits) {}

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
     * $total is the quoted order total; when known, SePay also carries the
     * deposit the customer could transfer instead of the full amount, worked
     * out from the order lines and the delivery province (see DepositPolicy).
     * A province set to "Bắt buộc chuyển khoản" shows COD as unavailable.
     *
     * @param  array<int, array{product_id: int, quantity: int, line_total: int}>  $lines
     * @return array<int, array{code: string, label: string, description: string, available: bool, disabled_reason: string|null, deposit_amount?: int|null}>
     */
    public function checkoutMethods(?int $total = null, array $lines = [], ?string $provinceCode = null, ?int $requiredDeposit = null): array
    {
        $methods = [];
        if ($this->codAvailable()) {
            $codAllowed = $this->deposits->codAllowed($provinceCode) && $requiredDeposit === null;
            $methods[] = [
                'code' => 'cod',
                'label' => 'Thanh toán khi nhận hàng (COD)',
                'description' => 'Thanh toán bằng tiền mặt khi nhận hàng.',
                'available' => $codAllowed,
                'disabled_reason' => $codAllowed ? null : ($requiredDeposit !== null ? self::DEPOSIT_REQUIRED_MESSAGE : self::COD_PROVINCE_MESSAGE),
            ];
        }
        if ($this->sepayAvailable()) {
            $methods[] = [
                'code' => 'sepay',
                'label' => 'Chuyển khoản qua SePay',
                'description' => 'Quét mã QR sau khi đơn hàng được ghi nhận.',
                'available' => true,
                'disabled_reason' => null,
                'deposit_amount' => $requiredDeposit
                    ?? ($total === null ? null : $this->deposits->amountForOrder($total, $lines, $provinceCode)),
                // The customer must pay this deposit (or everything) by transfer; COD is closed.
                'deposit_required' => $requiredDeposit !== null,
            ];
        }

        return $methods;
    }

    /**
     * The deposit this order must pay before the shop handles it, or null
     * (see DepositPolicy::requiredAmount). Never required while SePay transfer
     * is not set up, since the customer could not pay it.
     *
     * @param  array<int, array{product_id: int, quantity: int, line_total: int}>  $lines
     */
    public function requiredDeposit(?int $total, array $lines, ?string $provinceCode, string $shippingMethod): ?int
    {
        if ($total === null || ! $this->sepayAvailable()) {
            return null;
        }

        return $this->deposits->requiredAmount($total, $lines, $provinceCode, $shippingMethod);
    }

    /**
     * $provinceCode: the delivery province, for the "no COD" province rule;
     * $depositRequired: COD is closed when the order must pay a deposit first.
     */
    public function isAvailable(string $method, ?string $provinceCode = null, bool $depositRequired = false): bool
    {
        return match ($method) {
            'cod' => $this->codAvailable() && $this->deposits->codAllowed($provinceCode) && ! $depositRequired,
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
