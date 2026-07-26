<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use App\Models\IntegrationOutboxEvent;
use App\Models\Order;
use App\Models\Product;
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

                $requested = collect($data['items'])->groupBy('product_id')->map(fn ($rows) => $rows->sum('quantity'));
                $products = Product::whereIn('id', $requested->keys())->lockForUpdate()->get()->keyBy('id');
                $subtotal = 0;
                $weight = 0;
                $snapshots = [];

                foreach ($requested as $productId => $quantity) {
                    $product = $products->get($productId);
                    if (! $product || ! $this->purchasability->isPurchasable($product, (int) $quantity)) {
                        throw new KiotIntegrationException(
                            'INSUFFICIENT_AVAILABLE_STOCK',
                            $product ? "Sản phẩm {$product->name} không đủ số lượng khả dụng." : 'Sản phẩm không tồn tại.',
                            'business_rejection',
                            422,
                        );
                    }
                    $unitPrice = $this->purchasability->unitPrice($product);
                    $lineTotal = $unitPrice * (int) $quantity;
                    $subtotal += $lineTotal;
                    $weight += (int) ($product->weight ?? 0) * (int) $quantity;
                    $snapshots[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'quantity' => (int) $quantity,
                        'price' => $unitPrice,
                        'total' => $lineTotal,
                    ];
                }

                $shippingFee = $subtotal >= 500000 ? 0 : 30000;
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
}
