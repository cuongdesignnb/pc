<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\KiotIntegrationException;
use App\Http\Controllers\Controller;
use App\Jobs\Integrations\Kiot\ProcessKiotOutboxEvent;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Integrations\Kiot\KiotOrderCancellationService;
use App\Services\Integrations\Kiot\KiotOrderService;
use App\Services\Payments\SepayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['items.product'])->latest()->paginate(10);

        return response()->json([
            'orders' => $orders->getCollection()->map(fn (Order $order) => $this->present($order)),
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrderAccess($request, $order);
        $order->load('items');

        return response()->json(array_merge($this->present($order), [
            'payment' => $order->canPay() ? $this->generateSepayPaymentData($order) : null,
        ]));
    }

    public function store(Request $request, KiotOrderService $orders): JsonResponse
    {
        $validated = $request->validate([
            'checkout_idempotency_key' => 'required|uuid',
            'order_access_token' => 'required|uuid',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string|max:500',
            'shipping_city' => 'required|string|max:100',
            'shipping_district' => 'nullable|string|max:100',
            'shipping_ward' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'required|in:sepay,cod',
            'checkout_mode' => 'nullable|in:cart,buy_now',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validated['payment_method'] === 'cod' && ! $this->booleanSetting('payment_cod_enabled', true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Phương thức thanh toán COD hiện đang tắt.',
            ]);
        }

        try {
            $result = $orders->create($validated, $this->authenticatedUserId($request));
        } catch (KiotIntegrationException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'error_code' => $exception->errorCode], $exception->httpStatus ?? 503);
        }

        if ($result['outbox_id']) {
            ProcessKiotOutboxEvent::dispatchSync($result['outbox_id']);
        }
        $order = Order::with(['items.product'])->findOrFail($result['order']->id);

        if ($order->kiot_sync_status === 'rejected') {
            return response()->json([
                'message' => $this->friendlyError($order->kiot_sync_error_code),
                'order' => $this->present($order), 'integration_status' => 'rejected',
            ], 422);
        }

        if (($validated['checkout_mode'] ?? 'cart') === 'cart'
            && ! $result['duplicate']
            && in_array($order->kiot_sync_status, ['synced', 'retrying', 'not_required'], true)) {
            $this->clearCart($request);
        }

        $payment = $order->canPay() ? $this->generateSepayPaymentData($order) : null;
        $message = $order->kiot_sync_status === 'retrying'
            ? 'Đơn hàng đã được ghi nhận và đang chờ hệ thống kho xác nhận.'
            : 'Đặt hàng thành công';
        $status = $result['duplicate'] ? 200 : ($order->kiot_sync_status === 'retrying' ? 202 : 201);

        return response()->json([
            'message' => $message, 'order' => $this->present($order),
            'payment' => $payment, 'integration_status' => $order->kiot_sync_status,
        ], $status);
    }

    public function checkPayment(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrderAccess($request, $order);

        return response()->json([
            'paid' => $order->payment_status === 'paid',
            'payment_status' => $order->payment_status, 'order_status' => $order->order_status,
            'kiot_sync_status' => $order->kiot_sync_status, 'kiot_order_code' => $order->kiot_order_code,
            'kiot_sync_error_code' => $order->kiot_sync_error_code,
            'can_pay' => $order->canPay(), 'can_cancel' => $order->canCancel(),
            'payment' => $order->canPay() ? $this->generateSepayPaymentData($order) : null,
        ]);
    }

    public function sepayCallback(Request $request, SepayPaymentService $payments): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer',
            'transferAmount' => 'required|numeric|min:0',
            'content' => 'nullable|string',
            'gateway' => 'nullable|string|max:100',
            'referenceNumber' => 'nullable|string|max:255',
            'transactionDate' => 'nullable|date',
        ]);

        $webhookKey = config('services.sepay.webhook_key');
        if (! is_string($webhookKey) || $webhookKey === '') {
            Log::error('SePay IPN rejected: webhook authentication is not configured');

            return response()->json(['success' => false, 'message' => 'Webhook is not configured'], 503);
        }
        $authorization = (string) $request->header('Authorization');
        $providedKey = str_starts_with($authorization, 'Apikey ') ? substr($authorization, 7) : '';
        if ($providedKey === '' || ! hash_equals($webhookKey, $providedKey)) {
            Log::warning('SePay IPN rejected: invalid API key');

            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $transactionId = (int) $request->input('id');
        $content = (string) $request->input('content', '');
        if (! preg_match('/DH\d{12}/', $content, $matches)) {
            return response()->json(['success' => true, 'message' => 'No matching order']);
        }

        $result = $payments->record(
            'sepay_webhook',
            $transactionId,
            $matches[0],
            (int) $request->input('transferAmount'),
            $request->input('referenceNumber'),
        );

        return response()->json([
            'success' => true,
            'duplicate' => $result['duplicate'],
            'payment_status' => $result['processed'] ? 'paid' : 'pending_reconciliation',
        ]);
    }

    public function cancel(Request $request, Order $order, KiotOrderCancellationService $cancellation): JsonResponse
    {
        $this->authorizeOrderAccess($request, $order);
        $validated = $request->validate(['reason' => 'nullable|string|max:500']);
        if (! $order->canCancel()) {
            return response()->json(['message' => 'Không thể hủy đơn hàng này'], 422);
        }

        try {
            $order = $cancellation->cancel($order, $validated['reason'] ?? 'Khách hàng yêu cầu hủy');
        } catch (KiotIntegrationException $exception) {
            $message = in_array($exception->errorCode, ['ORDER_ALREADY_INVOICED', 'ORDER_NOT_CANCELLABLE'], true)
                ? 'Đơn hàng đã được xử lý trong hệ thống kho và không thể hủy trực tuyến.'
                : $exception->getMessage();

            return response()->json(['message' => $message, 'error_code' => $exception->errorCode], 422);
        }

        return response()->json(['message' => $order->order_status === 'cancelled' ? 'Đơn hàng đã được hủy' : 'Yêu cầu hủy đang được xử lý', 'order' => $this->present($order)]);
    }

    private function present(Order $order): array
    {
        $result = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'shipping_fee' => $order->shipping_fee,
            'total' => $order->total,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'checkout_mode' => $order->checkout_mode,
            'order_status' => $order->order_status,
            'shipping_name' => $order->shipping_name,
            'shipping_phone' => $order->shipping_phone,
            'customer_email' => $order->customer_email,
            'shipping_address' => $order->shipping_address,
            'shipping_city' => $order->shipping_city,
            'shipping_district' => $order->shipping_district,
            'shipping_ward' => $order->shipping_ward,
            'notes' => $order->notes,
            'created_at' => $order->created_at,
            'kiot_sync_status' => $order->kiot_sync_status,
            'kiot_order_code' => $order->kiot_order_code,
            'kiot_sync_error_code' => $order->kiot_sync_error_code,
            'can_pay' => $order->canPay(), 'can_cancel' => $order->canCancel(),
        ];
        if ($order->relationLoaded('items')) {
            $result['items'] = $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->total,
            ])->values()->all();
        }

        return $result;
    }

    private function authorizeOrderAccess(Request $request, Order $order): void
    {
        $userId = $this->authenticatedUserId($request);
        $authorized = $order->user_id !== null
            ? $userId === (int) $order->user_id
            : $order->matchesAccessToken($request->header('X-Order-Access-Token'));

        abort_unless($authorized, 404);
    }

    private function authenticatedUserId(Request $request): ?int
    {
        return $request->user('sanctum')?->id ?? $request->user()?->id;
    }

    private function clearCart(Request $request): void
    {
        $userId = $this->authenticatedUserId($request);
        $query = $userId
            ? Cart::where('user_id', $userId)
            : Cart::where('session_id', $request->header('X-Cart-Session') ?? session()->getId());
        $query->delete();
    }

    private function generateSepayPaymentData(Order $order): array
    {
        $sepay = config('services.sepay');
        $bankCode = $this->stringSetting('payment_bank_name', (string) ($sepay['bank_code'] ?? ''));
        $bankAccount = $this->stringSetting('payment_bank_account', (string) ($sepay['bank_account'] ?? ''));
        $accountName = $this->stringSetting('payment_bank_holder', (string) ($sepay['account_name'] ?? ''));

        return [
            'qr_url' => "https://img.vietqr.io/image/{$bankCode}-{$bankAccount}-qr_only.png?amount={$order->total}&addInfo=".urlencode($order->order_number).'&accountName='.urlencode($accountName),
            'bank_code' => $bankCode, 'bank_account' => $bankAccount,
            'account_name' => $accountName, 'amount' => (int) $order->total,
            'transfer_content' => $order->order_number, 'order_number' => $order->order_number,
        ];
    }

    private function stringSetting(string $key, string $fallback): string
    {
        $value = Setting::get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    private function booleanSetting(string $key, bool $fallback): bool
    {
        $value = Setting::get($key);

        return $value === null ? $fallback : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function friendlyError(?string $code): string
    {
        return match ($code) {
            'INSUFFICIENT_AVAILABLE_STOCK' => 'Một hoặc nhiều sản phẩm không còn đủ tồn kho.',
            'UNKNOWN_SKU' => 'Một hoặc nhiều sản phẩm chưa được nhận diện trong hệ thống kho.',
            'ORDER_TOTAL_MISMATCH' => 'Tổng tiền đơn hàng chưa khớp với hệ thống kho.',
            default => 'Đơn hàng không thể được hệ thống kho xác nhận.',
        };
    }
}
