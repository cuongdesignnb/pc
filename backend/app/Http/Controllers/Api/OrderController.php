<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\KiotIntegrationException;
use App\Http\Controllers\Controller;
use App\Jobs\Integrations\Kiot\ProcessKiotOutboxEvent;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\Integrations\Kiot\KiotOrderCancellationService;
use App\Services\Integrations\Kiot\KiotOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        if ($order->user_id && $order->user_id !== $request->user()?->id) {
            abort(403, 'Unauthorized');
        }
        $order->load(['items.product', 'transaction']);

        return response()->json(array_merge($this->present($order), [
            'payment' => $order->canPay() ? $this->generateSepayPaymentData($order) : null,
        ]));
    }

    public function store(Request $request, KiotOrderService $orders): JsonResponse
    {
        $validated = $request->validate([
            'checkout_idempotency_key' => 'required|uuid',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string|max:500',
            'shipping_city' => 'required|string|max:100',
            'shipping_district' => 'nullable|string|max:100',
            'shipping_ward' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'required|in:sepay,cod',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $result = $orders->create($validated, $request->user()?->id);
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

        if (! $result['duplicate'] && in_array($order->kiot_sync_status, ['synced', 'retrying', 'not_required'], true)) {
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

    public function checkPayment(Order $order): JsonResponse
    {
        return response()->json([
            'paid' => $order->payment_status === 'paid',
            'payment_status' => $order->payment_status, 'order_status' => $order->order_status,
            'kiot_sync_status' => $order->kiot_sync_status, 'kiot_order_code' => $order->kiot_order_code,
            'kiot_sync_error_code' => $order->kiot_sync_error_code,
            'can_pay' => $order->canPay(), 'can_cancel' => $order->canCancel(),
            'payment' => $order->canPay() ? $this->generateSepayPaymentData($order) : null,
        ]);
    }

    public function sepayCallback(Request $request): JsonResponse
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
        if ($webhookKey && $request->header('Authorization') !== "Apikey {$webhookKey}") {
            Log::warning('SePay IPN rejected: invalid API key');

            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $transactionId = $request->input('id');
        if ($transactionId && Transaction::where('sepay_transaction_id', $transactionId)->exists()) {
            return response()->json(['success' => true, 'duplicate' => true]);
        }
        $content = (string) $request->input('content', '');
        if (! preg_match('/DH\d{12}/', $content, $matches)) {
            return response()->json(['success' => true, 'message' => 'No matching order']);
        }

        $order = Order::where('order_number', $matches[0])->first();
        if (! $order || $order->kiot_sync_status !== 'synced') {
            return response()->json(['success' => true, 'message' => 'Order is not accepted by KIOT']);
        }
        if ((int) $request->input('transferAmount', 0) < (int) $order->total) {
            return response()->json(['success' => true, 'message' => 'Amount mismatch']);
        }

        DB::transaction(function () use ($request, $order, $transactionId, $content) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            if ($transactionId && Transaction::where('sepay_transaction_id', $transactionId)->exists()) {
                return;
            }
            $locked->update(['payment_status' => 'paid', 'order_status' => 'confirmed', 'paid_at' => now()]);
            Transaction::create([
                'order_id' => $locked->id, 'sepay_transaction_id' => $transactionId,
                'gateway' => $request->input('gateway', 'sepay'), 'amount' => $request->input('transferAmount'),
                'reference_code' => $request->input('referenceNumber'), 'content' => $content,
                'transaction_date' => $request->filled('transactionDate') ? \Carbon\Carbon::parse($request->input('transactionDate')) : now(),
            ]);
        });

        Log::info('SePay payment confirmed', ['order_id' => $order->id, 'order_number' => $order->order_number]);

        return response()->json(['success' => true]);
    }

    public function cancel(Request $request, Order $order, KiotOrderCancellationService $cancellation): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }
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
        return array_merge($order->toArray(), [
            'kiot_sync_status' => $order->kiot_sync_status,
            'kiot_order_code' => $order->kiot_order_code,
            'kiot_sync_error_code' => $order->kiot_sync_error_code,
            'can_pay' => $order->canPay(), 'can_cancel' => $order->canCancel(),
        ]);
    }

    private function clearCart(Request $request): void
    {
        $query = $request->user()
            ? Cart::where('user_id', $request->user()->id)
            : Cart::where('session_id', $request->header('X-Cart-Session') ?? session()->getId());
        $query->delete();
    }

    private function generateSepayPaymentData(Order $order): array
    {
        $sepay = config('services.sepay');

        return [
            'qr_url' => "https://img.vietqr.io/image/{$sepay['bank_code']}-{$sepay['bank_account']}-qr_only.png?amount={$order->total}&addInfo=".urlencode($order->order_number).'&accountName='.urlencode($sepay['account_name']),
            'bank_code' => $sepay['bank_code'], 'bank_account' => $sepay['bank_account'],
            'account_name' => $sepay['account_name'], 'amount' => (int) $order->total,
            'transfer_content' => $order->order_number, 'order_number' => $order->order_number,
        ];
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
