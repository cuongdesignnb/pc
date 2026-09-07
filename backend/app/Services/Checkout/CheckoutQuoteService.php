<?php

namespace App\Services\Checkout;

use App\Exceptions\CheckoutQuoteException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Catalog\ProductPurchasabilityService;
use App\Services\Locations\LocationDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CheckoutQuoteService
{
    private const TTL_MINUTES = 15;

    public function __construct(
        private readonly LocationDirectory $locations,
        private readonly PaymentMethodAvailability $paymentAvailability,
        private readonly ShippingCalculator $shippingCalculator,
        private readonly ProductPurchasabilityService $purchasability,
    ) {}

    /** @return array<string, mixed> */
    public function create(Request $request, array $data): array
    {
        $mode = $data['checkout_mode'];
        $requested = $mode === 'cart'
            ? $this->cartItems($request)
            : $this->normaliseItems($data['items'] ?? []);
        $calculated = $this->calculateLines($requested);
        $issues = $calculated['issues'];

        $provinceCode = $this->code($data['shipping_province_code'] ?? null);
        $wardCode = $this->code($data['shipping_ward_code'] ?? null);
        $location = null;
        if ($provinceCode === '' && $wardCode === '') {
            $issues[] = $this->issue('location', 'LOCATION_REQUIRED', 'Vui lòng chọn tỉnh/thành phố và xã/phường để tính phí giao hàng.');
        } elseif ($provinceCode === '' || $wardCode === '') {
            $issues[] = $this->issue('location', 'LOCATION_INCOMPLETE', 'Vui lòng chọn đủ tỉnh/thành phố và xã/phường.');
        } else {
            $location = $this->locations->resolve($provinceCode, $wardCode);
            if ($location === null) {
                $issues[] = $this->issue('location', 'LOCATION_INVALID', 'Xã/phường không thuộc tỉnh/thành phố đã chọn.');
            }
        }

        $shippingMethod = (string) ($data['shipping_method'] ?? 'standard');
        $shipping = $this->shippingCalculator->quote(
            $calculated['summary']['subtotal'],
            $calculated['summary']['total_quantity'],
            $shippingMethod,
        );
        if (! $shipping['available']) {
            $issues[] = $this->issue('shipping_method', 'SHIPPING_METHOD_UNAVAILABLE', $shipping['disabled_reason']);
        }

        $paymentMethod = $this->nullableString($data['payment_method'] ?? null);
        $paymentMethods = $this->paymentAvailability->checkoutMethods();
        if ($paymentMethod !== null && ! $this->paymentAvailability->isAvailable($paymentMethod)) {
            $issues[] = $this->issue('payment_method', 'PAYMENT_METHOD_UNAVAILABLE', 'Phương thức thanh toán này hiện không khả dụng.');
        }

        $shippingFee = $location !== null && $shipping['available'] && $calculated['summary']['total_quantity'] > 0
            ? $shipping['fee']
            : null;
        $summary = [
            'line_count' => $calculated['summary']['line_count'],
            'total_quantity' => $calculated['summary']['total_quantity'],
            'original_subtotal' => $calculated['summary']['original_subtotal'],
            'product_discount' => $calculated['summary']['product_discount'],
            'subtotal' => $calculated['summary']['subtotal'],
            'coupon_discount' => 0,
            'shipping_fee' => $shippingFee,
            'total' => $shippingFee === null ? null : $calculated['summary']['subtotal'] + $shippingFee,
            'tax_label' => null,
        ];
        if ($summary['total_quantity'] === 0) {
            $issues[] = $this->issue('items', 'EMPTY_CHECKOUT', 'Không có sản phẩm hợp lệ để thanh toán.');
        }

        $quoteId = (string) Str::uuid();
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);
        $response = [
            'quote_id' => $quoteId,
            'expires_at' => $expiresAt->toIso8601String(),
            'checkout_mode' => $mode,
            'currency' => $this->currency(),
            'items' => $calculated['lines'],
            'summary' => $summary,
            'shipping_methods' => [[
                'code' => $shipping['code'],
                'label' => $shipping['label'],
                'description' => $shipping['description'],
                'available' => $shipping['available'],
                'disabled_reason' => $shipping['disabled_reason'],
                'fee' => $shippingFee,
                'eta' => $shipping['eta'],
            ]],
            'payment_methods' => $paymentMethods,
            'coupon' => null,
            'capabilities' => [
                'coupons' => false,
                'saved_addresses' => false,
                'marketing_subscription' => false,
            ],
            'issues' => array_values($issues),
            'can_place_order' => $this->canPlaceOrder($summary, $issues, $paymentMethod),
        ];

        Cache::put($this->cacheKey($quoteId), [
            'owner' => $this->owner($request),
            'checkout_mode' => $mode,
            'items' => $this->canonicalItems($requested),
            'shipping_province_code' => $provinceCode,
            'shipping_ward_code' => $wardCode,
            'shipping_method' => $shippingMethod,
            'payment_method' => $paymentMethod,
            'summary' => $summary,
        ], $expiresAt);

        return $response;
    }

    /**
     * Re-check a quote before the order service recalculates and persists the
     * order. Existing idempotent attempts are allowed to replay after quote
     * expiry by the controller before this method is called.
     */
    public function assertMatches(Request $request, string $quoteId, array $data): void
    {
        $stored = Cache::get($this->cacheKey($quoteId));
        if (! is_array($stored)) {
            throw new CheckoutQuoteException('Báo giá checkout đã hết hạn. Vui lòng cập nhật lại thông tin đơn hàng.', 'CHECKOUT_QUOTE_EXPIRED');
        }
        if (($stored['owner'] ?? null) !== $this->owner($request)) {
            throw new CheckoutQuoteException('Báo giá checkout không thuộc phiên mua hàng hiện tại.', 'CHECKOUT_QUOTE_OWNER_MISMATCH', 403);
        }
        if (($stored['checkout_mode'] ?? null) !== ($data['checkout_mode'] ?? 'cart')) {
            throw new CheckoutQuoteException('Ngữ cảnh checkout đã thay đổi. Vui lòng cập nhật lại báo giá.', 'CHECKOUT_QUOTE_STALE');
        }

        $requested = $this->normaliseItems($data['items'] ?? []);
        if ($this->canonicalItems($requested) !== ($stored['items'] ?? [])) {
            throw new CheckoutQuoteException('Sản phẩm hoặc số lượng đã thay đổi. Vui lòng cập nhật lại báo giá.', 'CHECKOUT_QUOTE_STALE');
        }
        foreach (['shipping_province_code', 'shipping_ward_code', 'shipping_method', 'payment_method'] as $field) {
            $current = $field === 'shipping_method'
                ? (string) ($data[$field] ?? 'standard')
                : ($field === 'payment_method' ? $this->nullableString($data[$field] ?? null) : $this->code($data[$field] ?? null));
            if ($current !== ($stored[$field] ?? null)) {
                throw new CheckoutQuoteException('Thông tin giao hàng hoặc thanh toán đã thay đổi. Vui lòng cập nhật lại báo giá.', 'CHECKOUT_QUOTE_STALE');
            }
        }

        $calculated = $this->calculateLines($requested);
        if ($calculated['issues'] !== []
            || $calculated['summary']['subtotal'] !== (int) ($stored['summary']['subtotal'] ?? -1)
            || $calculated['summary']['total_quantity'] !== (int) ($stored['summary']['total_quantity'] ?? -1)) {
            throw new CheckoutQuoteException('Giá hoặc tình trạng sản phẩm đã thay đổi. Vui lòng xem lại báo giá trước khi đặt hàng.', 'CHECKOUT_QUOTE_STALE');
        }
        $provinceCode = $this->code($data['shipping_province_code'] ?? null);
        $wardCode = $this->code($data['shipping_ward_code'] ?? null);
        if ($this->locations->resolve($provinceCode, $wardCode) === null) {
            throw new CheckoutQuoteException('Địa chỉ giao hàng không hợp lệ.', 'LOCATION_INVALID', 422);
        }
        $shipping = $this->shippingCalculator->quote(
            $calculated['summary']['subtotal'],
            $calculated['summary']['total_quantity'],
            (string) ($data['shipping_method'] ?? 'standard'),
        );
        if (($stored['summary']['shipping_fee'] ?? null) !== $shipping['fee']) {
            throw new CheckoutQuoteException('Phí giao hàng đã thay đổi. Vui lòng cập nhật lại báo giá.', 'CHECKOUT_QUOTE_STALE');
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function cartItems(Request $request): array
    {
        $user = $request->user('sanctum') ?? $request->user();
        $cart = $user
            ? Cart::where('user_id', $user->id)->first()
            : Cart::where('session_id', $request->header('X-Cart-Session') ?? session()->getId())->first();
        if (! $cart) {
            return [];
        }

        return $cart->items()
            ->with(['product.images', 'variant'])
            ->where('is_selected', true)
            ->get()
            ->map(fn (CartItem $item): array => [
                'product_id' => (int) $item->product_id,
                'variant_id' => $item->variant_id === null ? null : (int) $item->variant_id,
                'quantity' => (int) $item->quantity,
            ])
            ->all();
    }

    /** @return array<int, array{product_id: int, variant_id: int|null, quantity: int}> */
    private function normaliseItems(array $items): array
    {
        return collect($items)
            ->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'variant_id' => empty($item['variant_id']) ? null : (int) $item['variant_id'],
                'quantity' => (int) $item['quantity'],
            ])
            ->groupBy(fn (array $item): string => $item['product_id'].':'.($item['variant_id'] ?? 0))
            ->map(function ($rows): array {
                $first = $rows->first();

                return [
                    'product_id' => $first['product_id'],
                    'variant_id' => $first['variant_id'],
                    'quantity' => (int) $rows->sum('quantity'),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array{lines: array<int, array<string, mixed>>, summary: array<string, int>, issues: array<int, array<string, string>>} */
    private function calculateLines(array $requested): array
    {
        $products = Product::query()
            ->whereIn('id', collect($requested)->pluck('product_id')->all())
            ->with('images')
            ->get()
            ->keyBy('id');
        $variants = ProductVariant::query()
            ->whereIn('id', collect($requested)->pluck('variant_id')->filter()->all())
            ->get()
            ->keyBy('id');
        $lines = [];
        $issues = [];
        $originalSubtotal = 0;
        $subtotal = 0;
        $productDiscount = 0;
        $quantity = 0;

        foreach ($requested as $item) {
            $product = $products->get($item['product_id']);
            $variant = $item['variant_id'] ? $variants->get($item['variant_id']) : null;
            $itemQuantity = (int) $item['quantity'];
            $valid = $itemQuantity > 0 && $product !== null;
            if ($valid && $variant !== null) {
                $valid = $variant->product_id === $product->id
                    && $variant->is_active
                    && $variant->stock_quantity >= $itemQuantity
                    && $product->isVisibleOnStorefront();
            } elseif ($valid && $item['variant_id'] !== null) {
                $valid = false;
            } elseif ($valid) {
                $valid = $this->purchasability->isPurchasable($product, $itemQuantity);
            }
            if (! $valid) {
                $issues[] = $this->issue('items', 'ITEM_UNAVAILABLE', $product
                    ? "Sản phẩm {$product->name} hoặc biến thể đã chọn không còn khả dụng."
                    : 'Sản phẩm không còn tồn tại.');

                continue;
            }

            $unitPrice = $variant?->display_price ?? $this->purchasability->unitPrice($product);
            $originalUnitPrice = (int) ($variant?->price ?? $product->price);
            $lineTotal = $unitPrice * $itemQuantity;
            $lineOriginal = $originalUnitPrice * $itemQuantity;
            $saving = max(0, $lineOriginal - $lineTotal);
            $originalSubtotal += $lineOriginal;
            $subtotal += $lineTotal;
            $productDiscount += $saving;
            $quantity += $itemQuantity;
            $lines[] = [
                'key' => $product->id.':'.($variant?->id ?? 'base'),
                'product_id' => (int) $product->id,
                'variant_id' => $variant?->id === null ? null : (int) $variant->id,
                'name' => $product->name,
                'variant_name' => $variant?->name,
                'sku' => $variant?->sku ?: $product->sku,
                'quantity' => $itemQuantity,
                'unit_price' => (int) $unitPrice,
                'original_unit_price' => $originalUnitPrice,
                'line_total' => $lineTotal,
                'line_discount' => $saving,
                'image' => $product->images->first()?->url,
            ];
        }

        return [
            'lines' => $lines,
            'summary' => [
                'line_count' => count($lines),
                'total_quantity' => $quantity,
                'original_subtotal' => $originalSubtotal,
                'product_discount' => $productDiscount,
                'subtotal' => $subtotal,
            ],
            'issues' => $issues,
        ];
    }

    /** @return array<int, array{product_id: int, variant_id: int|null, quantity: int}> */
    private function canonicalItems(array $items): array
    {
        return collect($items)
            ->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'variant_id' => empty($item['variant_id']) ? null : (int) $item['variant_id'],
                'quantity' => (int) $item['quantity'],
            ])
            ->sortBy(fn (array $item): string => $item['product_id'].':'.($item['variant_id'] ?? 0))
            ->values()
            ->all();
    }

    /** @return array{field: string, code: string, message: string} */
    private function issue(string $field, string $code, ?string $message): array
    {
        return ['field' => $field, 'code' => $code, 'message' => $message ?? 'Thông tin chưa hợp lệ.'];
    }

    /** @param array<int, array<string, string>> $issues */
    private function canPlaceOrder(array $summary, array $issues, ?string $paymentMethod): bool
    {
        return $summary['total_quantity'] > 0
            && $issues === []
            && $paymentMethod !== null
            && $this->paymentAvailability->isAvailable($paymentMethod);
    }

    /** @return array{user_id: int|null, session_id: string|null} */
    private function owner(Request $request): array
    {
        $user = $request->user('sanctum') ?? $request->user();

        return [
            'user_id' => $user?->id,
            'session_id' => $user ? null : (string) ($request->header('X-Cart-Session') ?? session()->getId()),
        ];
    }

    private function cacheKey(string $quoteId): string
    {
        return 'checkout_quote:'.$quoteId;
    }

    private function code(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function currency(): string
    {
        $currency = Setting::get('currency', 'VND');

        return is_string($currency) && trim($currency) !== '' ? trim($currency) : 'VND';
    }
}
