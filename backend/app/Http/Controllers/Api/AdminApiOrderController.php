<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AdminApi\AdminApiTokens;
use App\Services\Orders\OrderApiPresenter;
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
    public function __construct(private readonly OrderApiPresenter $presenter) {}

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

        $query = Order::query()->with(['items.product', 'affiliate', 'affiliateCommission']);
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

        return $this->noStore(response()->json(['data' => $this->payload($order->load(['items.product', 'affiliate', 'affiliateCommission']))]));
    }

    /** @return array<string, mixed> */
    private function payload(Order $order): array
    {
        return $this->presenter->present($order);
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

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store, private');
    }
}
