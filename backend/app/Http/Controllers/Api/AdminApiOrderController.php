<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AdminApi\AdminApiTokens;
use App\Support\SiteTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Read-only order list for "orders" admin API tokens, used by the shop's own
 * tools (n8n polling for new orders). Never changes an order. Payment links,
 * access tokens and KIOT internals are left out.
 */
class AdminApiOrderController extends Controller
{
    private const ORDER_STATUSES = ['pending', 'confirmed', 'processing', 'shipping', 'delivered', 'cancelled'];

    public function index(Request $request): JsonResponse
    {
        $this->ensureOwnerMayReadOrders($request);
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:200'],
            'payment_status' => ['nullable', 'string', 'max:200'],
            'created_since' => ['nullable', 'date'],
            'created_until' => ['nullable', 'date'],
            'after_id' => ['nullable', 'integer', 'min:0'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::query()->with(['items', 'affiliate', 'affiliateCommission']);
        if ($statuses = $this->csv($validated['status'] ?? null)) {
            $unknown = array_diff($statuses, self::ORDER_STATUSES);
            if ($unknown) {
                return response()->json([
                    'message' => 'Trạng thái không hợp lệ: '.implode(', ', $unknown).'. Dùng: '.implode(', ', self::ORDER_STATUSES).'.',
                ], 422);
            }
            $query->whereIn('order_status', $statuses);
        }
        if ($paymentStatuses = $this->csv($validated['payment_status'] ?? null)) {
            $query->whereIn('payment_status', $paymentStatuses);
        }
        if (filled($validated['created_since'] ?? null)) {
            $query->where('created_at', '>=', $this->time($validated['created_since']));
        }
        if (filled($validated['created_until'] ?? null)) {
            $query->where('created_at', '<=', $this->time($validated['created_until']));
        }
        if (filled($validated['search'] ?? null)) {
            $search = (string) $validated['search'];
            $query->where(fn ($q) => $q->where('order_number', 'like', "%{$search}%")
                ->orWhere('shipping_phone', 'like', "%{$search}%")
                ->orWhere('shipping_name', 'like', "%{$search}%"));
        }
        // after_id: oldest first so a poller can keep the last id it has seen.
        if (isset($validated['after_id'])) {
            $query->where('id', '>', (int) $validated['after_id'])->orderBy('id');
        } else {
            $query->orderByDesc('id');
        }

        $page = $query->paginate((int) ($validated['per_page'] ?? 50));

        return $this->noStore(response()->json([
            'data' => $page->getCollection()->map(fn (Order $order) => $this->payload($order))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]));
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->ensureOwnerMayReadOrders($request);

        return $this->noStore(response()->json(['data' => $this->payload($order->load(['items', 'affiliate', 'affiliateCommission']))]));
    }

    /** @return array<string, mixed> */
    private function payload(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->shipping_name,
            'customer_phone' => $order->shipping_phone,
            'customer_email' => $order->customer_email,
            'shipping_address' => collect([$order->shipping_address, $order->shipping_ward, $order->shipping_district, $order->shipping_city])
                ->filter(fn ($part) => filled($part))->implode(', '),
            'notes' => $order->notes,
            'items' => $order->items->map(fn (OrderItem $item) => [
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'variant' => $item->variant_name,
                'sku' => $item->sku,
                'quantity' => (int) $item->quantity,
                'price' => (int) $item->price,
                'warranty_months' => $item->warranty_months,
                'warranty_price' => (int) $item->warranty_price,
                'total' => (int) $item->total,
            ])->values()->all(),
            'subtotal' => (int) $order->subtotal,
            'discount' => (int) $order->discount,
            'shipping_fee' => (int) $order->shipping_fee,
            'total' => (int) $order->total,
            'status' => $order->order_status,
            'status_label' => $this->statusLabel((string) $order->order_status),
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'payment_option' => $order->payment_option,
            'amount_paid' => (int) $order->amount_paid,
            'referral_code' => $order->referral_code,
            // Shopee/Lazada-style affiliate (CTV): who brought the customer and what they earn.
            'affiliate_id' => $order->affiliate_id,
            'affiliate_code' => $order->affiliate?->code,
            'referral_discount' => (int) $order->referral_discount,
            'affiliate_commission' => $order->affiliateCommission ? (int) $order->affiliateCommission->amount : null,
            'affiliate_commission_status' => $order->affiliateCommission?->status,
            'affiliate_commission_status_label' => $this->commissionStatusLabel($order->affiliateCommission?->status),
            'affiliate_commission_approve_after' => $order->affiliateCommission?->approve_after?->toIso8601String(),
            // Where the order came from, as on the admin order page.
            'utm_source' => $order->utm_source,
            'utm_medium' => $order->utm_medium,
            'utm_campaign' => $order->utm_campaign,
            'utm_content' => $order->utm_content,
            'video_code' => $order->video_code,
            'landing_page' => $order->landing_page,
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'admin_url' => route('admin.orders.show', $order),
        ];
    }

    private function ensureOwnerMayReadOrders(Request $request): void
    {
        // The token keeps working only while its creator may still see orders.
        abort_unless(AdminApiTokens::mayReadOrders($request->user()), 403, 'Người tạo token không còn quyền xem đơn hàng.');
    }

    /** @return list<string> */
    private function csv(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), fn ($part) => $part !== ''));
    }

    private function time(string $value): Carbon
    {
        return Carbon::parse($value, SiteTimezone::get())->utc();
    }

    private function commissionStatusLabel(?string $status): ?string
    {
        return match ($status) {
            null => null,
            'pending' => 'Chờ duyệt',
            'approved' => 'Được rút',
            'cancelled' => 'Đã huỷ',
            default => $status,
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Chờ xử lý',
            'confirmed' => 'Đã xác nhận',
            'processing' => 'Đang xử lý',
            'shipping' => 'Đang giao',
            'delivered' => 'Đã giao hàng',
            'cancelled' => 'Đã hủy',
            default => $status,
        };
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store, private');
    }
}
