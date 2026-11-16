<?php

namespace App\Services\Checkout;

use App\Models\Setting;
use App\Services\Locations\LocationDirectory;

/**
 * Ways the customer can receive the order (Cài đặt → Vận chuyển), each with a
 * fixed fee (no carrier API yet):
 *
 *   standard  Giao hàng tiêu chuẩn: shipping_default_fee, free from shipping_free_threshold (as before)
 *   ghtk      Giao Hàng Tiết Kiệm: the standard fee rules, or its own fixed fee
 *   ahamove   Ahamove: fixed fee, only for the provinces picked (e.g. Hà Nội)
 *   pickup    Nhận tại cửa hàng: free, no delivery address needed
 *
 * Delivery methods (all but pickup) are free when the laptops in the order
 * cost at least shipping_free_laptop_min_subtotal (0 = rule off).
 */
class ShippingCalculator
{
    public const METHODS = ['standard', 'ghtk', 'ahamove', 'pickup'];

    private const DEFAULT_LABELS = [
        'standard' => 'Giao hàng tiêu chuẩn',
        'ghtk' => 'Giao Hàng Tiết Kiệm',
        'ahamove' => 'Ahamove (giao nhanh trong ngày)',
        'pickup' => 'Nhận tại cửa hàng',
    ];

    public function __construct(private readonly LocationDirectory $locations) {}

    /**
     * Every method the shop has turned on, priced for this order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function methods(int $subtotal, int $quantity, ?string $provinceCode = null, int $laptopSubtotal = 0): array
    {
        return array_map(
            fn (string $method): array => $this->quote($subtotal, $quantity, $method, $provinceCode, $laptopSubtotal),
            $this->enabledMethods(),
        );
    }

    /** @return array{code: string, label: string, description: string, available: bool, disabled_reason: string|null, fee: int, eta: string|null, requires_address: bool, pickup_address: string|null} */
    public function quote(int $subtotal, int $quantity, ?string $method = null, ?string $provinceCode = null, int $laptopSubtotal = 0): array
    {
        $method = $this->resolveMethod($method);
        if (! in_array($method, self::METHODS, true) || ! $this->isEnabled($method)) {
            return [
                'code' => $method,
                'label' => '',
                'description' => '',
                'available' => false,
                'disabled_reason' => 'Phương thức giao hàng chưa được hỗ trợ.',
                'fee' => 0,
                'eta' => null,
                'requires_address' => true,
                'pickup_address' => null,
            ];
        }

        $available = true;
        $reason = null;
        $description = $this->stringSetting("shipping_{$method}_description", match ($method) {
            'pickup' => 'Nhận hàng và thanh toán tại cửa hàng, không mất phí giao hàng.',
            'ahamove' => 'Giao nhanh trong ngày bằng Ahamove.',
            'ghtk' => 'Giao qua Giao Hàng Tiết Kiệm.',
            default => 'Phí giao hàng được xác nhận theo chính sách hiện tại.',
        });
        if ($method === 'pickup') {
            $fee = 0;
        } else {
            $fee = match ($method) {
                'ahamove' => $this->integerSetting('shipping_ahamove_fee', 50000),
                'ghtk' => $this->booleanSetting('shipping_ghtk_use_standard_fee', true)
                    ? $this->standardFee($subtotal)
                    : $this->integerSetting('shipping_ghtk_fee', 30000),
                default => $this->standardFee($subtotal),
            };
            $laptopThreshold = $this->laptopFreeThreshold();
            if ($laptopThreshold > 0 && $laptopSubtotal >= $laptopThreshold) {
                $fee = 0;
            }
            if ($method === 'ahamove' && ($codes = $this->ahamoveProvinceCodes()) !== []) {
                $names = $this->provinceNames($codes);
                $description .= ' Chỉ áp dụng tại: '.implode(', ', $names).'.';
                if (trim((string) $provinceCode) !== '' && ! in_array(trim((string) $provinceCode), $codes, true)) {
                    $available = false;
                    $reason = 'Ahamove chỉ giao tại: '.implode(', ', $names).'.';
                }
            }
        }

        return [
            'code' => $method,
            'label' => self::label($method),
            'description' => $description,
            'available' => $available,
            'disabled_reason' => $reason,
            'fee' => $quantity <= 0 ? 0 : $fee,
            'eta' => $this->nullableStringSetting("shipping_{$method}_eta"),
            'requires_address' => $method !== 'pickup',
            'pickup_address' => $method === 'pickup' ? $this->pickupAddress() : null,
        ];
    }

    /**
     * The method an order uses: the requested one, or the first enabled one
     * when none was sent. Older clients always send "standard"; when the shop
     * has turned standard off, that means its first enabled delivery method.
     */
    public function resolveMethod(?string $method): string
    {
        $method = trim((string) $method);
        if ($method === '') {
            return $this->defaultMethod();
        }
        if ($method === 'standard' && ! $this->isEnabled('standard')) {
            return $this->defaultMethod();
        }

        return $method;
    }

    public function defaultMethod(): string
    {
        $enabled = $this->enabledMethods();
        $delivery = array_values(array_filter($enabled, fn (string $method): bool => $method !== 'pickup'));

        return $delivery[0] ?? $enabled[0] ?? 'standard';
    }

    /** @return list<string> */
    public function enabledMethods(): array
    {
        $enabled = array_values(array_filter(self::METHODS, fn (string $method): bool => $this->isEnabled($method)));

        // Standard always stays when the shop turned everything off.
        return $enabled === [] ? ['standard'] : $enabled;
    }

    public function isEnabled(string $method): bool
    {
        return match ($method) {
            'standard' => $this->booleanSetting('shipping_standard_enabled', true)
                || ! array_filter(['ghtk', 'ahamove', 'pickup'], fn (string $other): bool => $this->isEnabled($other)),
            'ghtk', 'ahamove', 'pickup' => $this->booleanSetting("shipping_{$method}_enabled", false),
            default => false,
        };
    }

    public static function label(?string $method): string
    {
        $method = (string) ($method ?: 'standard');
        $value = Setting::get("shipping_{$method}_label");

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : (self::DEFAULT_LABELS[$method] ?? $method);
    }

    public function pickupAddress(): ?string
    {
        $address = $this->nullableStringSetting('shipping_pickup_address')
            ?? $this->nullableStringSetting('contact_address');

        return $address === null ? null : preg_replace('/\s*\R\s*/u', ', ', $address);
    }

    public function freeThreshold(): int
    {
        return $this->integerSetting('shipping_free_threshold', 500000);
    }

    public function defaultFee(): int
    {
        return $this->integerSetting('shipping_default_fee', 30000);
    }

    public function laptopFreeThreshold(): int
    {
        return $this->integerSetting('shipping_free_laptop_min_subtotal', 0);
    }

    /** @return list<string> */
    public function ahamoveProvinceCodes(): array
    {
        $codes = Setting::get('shipping_ahamove_province_codes', []);
        if (is_string($codes)) {
            $codes = json_decode($codes, true);
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($code): string => trim((string) $code), is_array($codes) ? $codes : []),
            fn (string $code): bool => $code !== '',
        )));
    }

    private function standardFee(int $subtotal): int
    {
        $threshold = $this->freeThreshold();

        return $threshold > 0 && $subtotal >= $threshold ? 0 : $this->defaultFee();
    }

    /** @param list<string> $codes @return list<string> */
    private function provinceNames(array $codes): array
    {
        $names = collect($this->locations->provinces())->pluck('name', 'code');

        return array_map(fn (string $code): string => (string) ($names[$code] ?? $code), $codes);
    }

    private function integerSetting(string $key, int $fallback): int
    {
        $value = Setting::get($key);

        return is_numeric($value) ? max(0, (int) $value) : $fallback;
    }

    private function booleanSetting(string $key, bool $fallback): bool
    {
        $value = Setting::get($key);

        return $value === null || $value === '' ? $fallback : filter_var($value, FILTER_VALIDATE_BOOLEAN);
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
