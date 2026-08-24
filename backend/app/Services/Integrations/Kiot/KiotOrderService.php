<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use App\Models\IntegrationOutboxEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Catalog\ProductPurchasabilityService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KiotOrderService
{
    public function __construct(
        private readonly KiotClient $client,
        private readonly KiotConfigurationResolver $resolver,
        private readonly ProductPurchasabilityService $purchasability,
    ) {}

    public function create(array $data, ?int $userId): array
    {
        if ($existing = Order::where('checkout_idempotency_key', $data['checkout_idempotency_key'])->first()) {
            return $this->existingResult($existing, $data['order_access_token'], $userId);
        }

        $runtime = $this->resolver->resolve();
        $enabled = $runtime->enabled && $runtime->orderSyncEnabled;
        if ($enabled) {
            $this->client->assertConfigured($runtime);
        }

        try {
            return DB::transaction(function () use ($data, $userId, $enabled) {
                if ($existing = Order::where('checkout_idempotency_key', $data['checkout_idempotency_key'])->lockForUpdate()->first()) {
                    return $this->existingResult($existing, $data['order_access_token'], $userId);
                }

                $requested = collect($data['items'])
                    ->groupBy(fn (array $item): string => (int) $item['product_id'].':'.(int) ($item['variant_id'] ?? 0))
                    ->map(function ($rows): array {
                        $first = $rows->first();

                        return [
                            'product_id' => (int) $first['product_id'],
                            'variant_id' => ! empty($first['variant_id']) ? (int) $first['variant_id'] : null,
                            'quantity' => $rows->sum('quantity'),
                        ];
                    });
                $products = Product::whereIn('id', $requested->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');
                $variants = ProductVariant::whereIn('id', $requested->pluck('variant_id')->filter())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $subtotal = 0;
                $weight = 0;
                $snapshots = [];

                foreach ($requested as $line) {
                    $product = $products->get($line['product_id']);
                    $variant = $line['variant_id'] ? $variants->get($line['variant_id']) : null;
                    $quantity = (int) $line['quantity'];
                    $isPurchasable = $variant
                        ? $variant->product_id === $product?->id
                            && $variant->is_active
                            && $variant->stock_quantity >= $quantity
                            && $product?->isVisibleOnStorefront()
                        : $product && $this->purchasability->isPurchasable($product, $quantity);
                    if (! $product || ! $isPurchasable) {
                        throw new KiotIntegrationException(
                            'INSUFFICIENT_AVAILABLE_STOCK',
                            $product ? "Sản phẩm {$product->name} không đủ số lượng khả dụng." : 'Sản phẩm không tồn tại.',
                            'business_rejection',
                            422,
                        );
                    }
                    $unitPrice = $variant?->display_price ?? $this->purchasability->unitPrice($product);
                    $lineTotal = $unitPrice * $quantity;
                    $subtotal += $lineTotal;
                    $weight += (int) ($product->weight ?? 0) * $quantity;
                    $snapshots[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'variant_id' => $variant?->id,
                        'variant_name' => $variant?->name,
                        'sku' => $variant?->sku ?: $product->sku,
                        'quantity' => $quantity,
                        'price' => $unitPrice,
                        'total' => $lineTotal,
                    ];
                }

                $freeShippingThreshold = $this->nonNegativeIntegerSetting('shipping_free_threshold', 500000);
                $defaultShippingFee = $this->nonNegativeIntegerSetting('shipping_default_fee', 30000);
                $shippingFee = $freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold
                    ? 0
                    : $defaultShippingFee;
                $eventId = $enabled ? (string) Str::uuid() : null;
                $idempotencyKey = $enabled ? (string) Str::uuid() : null;
                $order = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'user_id' => $userId,
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'shipping_fee' => $shippingFee,
                    'total' => $subtotal + $shippingFee,
                    'payment_method' => $data['payment_method'],
                    'payment_status' => 'unpaid',
                    'order_status' => 'pending',
                    'shipping_name' => $data['customer_name'],
                    'shipping_phone' => $data['customer_phone'],
                    'customer_email' => $data['customer_email'],
                    'shipping_address' => $data['shipping_address'],
                    'shipping_city' => $data['shipping_city'],
                    'shipping_district' => $data['shipping_district'] ?? null,
                    'shipping_ward' => $data['shipping_ward'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'checkout_idempotency_key' => $data['checkout_idempotency_key'],
                    'order_access_token_hash' => Order::hashAccessToken($data['order_access_token']),
                    'kiot_event_id' => $eventId,
                    'kiot_idempotency_key' => $idempotencyKey,
                    'kiot_sync_status' => $enabled ? 'pending' : 'not_required',
                ]);

                foreach ($snapshots as $snapshot) {
                    $order->items()->create($snapshot);
                }

                $outbox = null;
                if ($enabled) {
                    $payload = $this->createPayload($order->fresh()->load('items'), $weight);
                    $rawBody = $this->client->encode($payload);
                    $payloadHash = hash('sha256', $rawBody);
                    $order->update(['kiot_payload_hash' => $payloadHash]);
                    $outbox = IntegrationOutboxEvent::create([
                        'integration' => 'kiot', 'event_type' => 'order.create',
                        'aggregate_type' => Order::class, 'aggregate_id' => $order->id,
                        'event_id' => $eventId, 'idempotency_key' => $idempotencyKey,
                        'payload' => $payload, 'raw_body' => $rawBody, 'payload_hash' => $payloadHash,
                        'status' => 'pending', 'next_attempt_at' => now(),
                    ]);
                }

                return ['order' => $order, 'outbox_id' => $outbox?->id, 'duplicate' => false];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Order::where('checkout_idempotency_key', $data['checkout_idempotency_key'])->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->existingResult($existing, $data['order_access_token'], $userId);
        }
    }

    private function existingResult(Order $order, string $accessToken, ?int $userId): array
    {
        if ($order->user_id !== $userId || ! $order->matchesAccessToken($accessToken)) {
            throw new KiotIntegrationException(
                'IDEMPOTENCY_KEY_CONFLICT',
                'Khóa checkout đã được sử dụng cho một yêu cầu khác.',
                'fatal_conflict',
                409,
            );
        }

        return ['order' => $order, 'outbox_id' => null, 'duplicate' => true];
    }

    private function createPayload(Order $order, int $weight): array
    {
        return [
            'event_id' => $order->kiot_event_id,
            'external_order_id' => (string) $order->id,
            'external_order_code' => $order->order_number,
            'ordered_at' => $order->created_at->toRfc3339String(),
            'customer' => ['name' => $order->shipping_name, 'phone' => $order->shipping_phone, 'email' => $order->customer_email],
            'delivery' => [
                'is_delivery' => true, 'receiver_name' => $order->shipping_name,
                'receiver_phone' => $order->shipping_phone, 'receiver_address' => $order->shipping_address,
                'receiver_ward' => $order->shipping_ward, 'receiver_district' => $order->shipping_district,
                'receiver_city' => $order->shipping_city, 'weight' => $weight,
                'shipping_fee' => (int) $order->shipping_fee,
            ],
            'payment' => ['method' => $order->payment_method, 'status' => $order->payment_status],
            'totals' => [
                'subtotal' => (int) $order->subtotal, 'discount' => (int) $order->discount,
                'shipping_fee' => (int) $order->shipping_fee, 'total' => (int) $order->total,
            ],
            'items' => $order->items->map(fn ($item) => [
                'sku' => $item->sku, 'product_name' => $item->product_name,
                'quantity' => $item->quantity, 'unit_price' => (int) $item->price,
                'discount' => 0, 'line_total' => (int) $item->total, 'bundle_ref' => null,
            ])->values()->all(),
            'note' => $order->notes,
        ];
    }

    private function nonNegativeIntegerSetting(string $key, int $fallback): int
    {
        $value = Setting::get($key);

        return is_numeric($value) ? max(0, (int) $value) : $fallback;
    }
}
