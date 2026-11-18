<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CheckoutQuoteException;
use App\Exceptions\KiotIntegrationException;
use App\Http\Controllers\Controller;
use App\Jobs\Integrations\Kiot\ProcessKiotOutboxEvent;
use App\Jobs\Orders\SendNewOrderNotification;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Checkout\CheckoutQuoteService;
use App\Services\Checkout\PaymentMethodAvailability;
use App\Services\Checkout\ShippingCalculator;
use App\Services\Integrations\Kiot\KiotOrderCancellationService;
use App\Services\Integrations\Kiot\KiotOrderService;
use App\Services\Locations\LocationDirectory;
use App\Services\Orders\OrderHistoryRecorder;
use App\Services\Payments\SepayWebhookHandler;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private readonly PaymentMethodAvailability $paymentAvailability) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['items.product'])->latest()->paginate(10);

        return $this->noStore(response()->json([
            'orders' => $orders->getCollection()->map(fn (Order $order) => $this->present($order)),
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()],
        ]));
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrderAccess($request, $order);
        $order->load('items');

        return response()->json(array_merge($this->present($order), [
            'payment' => $order->canPay() && $this->paymentAvailability->sepayAvailable()
                ? $this->generateSepayPaymentData($order)
                : null,
        ]));
    }

    public function store(
        Request $request,
        KiotOrderService $orders,
        LocationDirectory $locations,
        CheckoutQuoteService $quotes,
    ): JsonResponse {
        $validated = $request->validate([
            'checkout_idempotency_key' => 'required|uuid',
            'order_access_token' => 'required|uuid',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            // Store pickup ("Nhận tại cửa hàng") needs no delivery address.
            'shipping_address' => 'required_unless:shipping_method,pickup|nullable|string|max:500',
            'shipping_city' => 'nullable|string|max:100',
            'shipping_district' => 'nullable|string|max:100',
            'shipping_ward' => 'nullable|string|max:100',
            'shipping_province_code' => 'nullable|string|max:20',
            'shipping_ward_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
            'referral_code' => 'nullable|string|max:20',
            'utm_source' => 'nullable|string|max:150',
            'utm_campaign' => 'nullable|string|max:150',
            'utm_content' => 'nullable|string|max:150',
            'utm_medium' => 'nullable|string|max:150',
            'utm_term' => 'nullable|string|max:150',
            'gclid' => 'nullable|string|max:255',
            'fbclid' => 'nullable|string|max:255',
            'landing_page' => 'nullable|string|max:500',
            'video_code' => 'nullable|string|max:100',
            'payment_method' => 'required|in:sepay,cod',
            'payment_option' => 'nullable|in:full,deposit',
            'shipping_method' => 'nullable|in:'.implode(',', ShippingCalculator::METHODS),
            'quote_id' => 'nullable|uuid',
            'checkout_mode' => 'nullable|in:cart,buy_now',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.warranty_months' => 'nullable|integer|min:1|max:120',
        ]);

        $validated['checkout_mode'] = $validated['checkout_mode'] ?? 'cart';
        $shipping = app(ShippingCalculator::class);
        $validated['shipping_method'] = $shipping->resolveMethod($validated['shipping_method'] ?? null);
        if ($validated['shipping_method'] === 'pickup') {
            $this->usePickupAddress($validated, $shipping);
        } else {
            $this->resolveShippingLocation($validated, $locations);
        }
        $hasExistingAttempt = Order::where('checkout_idempotency_key', $validated['checkout_idempotency_key'])->exists();
        if (! $hasExistingAttempt) {
            $this->useSelectedCartItems($request, $validated, ! empty($validated['quote_id']));
        }

        if (! $this->paymentAvailability->isAvailable($validated['payment_method'])) {
            throw ValidationException::withMessages([
                'payment_method' => 'Phương thức thanh toán này hiện không khả dụng.',
            ]);
        }
        if (! $this->paymentAvailability->isAvailable($validated['payment_method'], $validated['shipping_province_code'] ?? null)) {
            throw ValidationException::withMessages(['payment_method' => PaymentMethodAvailability::COD_PROVINCE_MESSAGE]);
        }

        if (! $hasExistingAttempt && ! empty($validated['quote_id'])) {
            try {
                $quotes->assertMatches($request, (string) $validated['quote_id'], $validated);
            } catch (CheckoutQuoteException $exception) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'error_code' => $exception->errorCode,
                ], $exception->httpStatus);
            }
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

        if (! $result['duplicate']) {
            SendNewOrderNotification::dispatch($order->id);
            app(WebhookDispatcher::class)->orderCreated($order);
        }

        if (($validated['checkout_mode'] ?? 'cart') === 'cart'
            && ! $result['duplicate']
            && in_array($order->kiot_sync_status, ['synced', 'retrying', 'not_required', 'rejected', 'failed', 'waiting_payment'], true)) {
            $this->clearCart($request);
        }

        $payment = $order->canPay() && $this->paymentAvailability->sepayAvailable()
            ? $this->generateSepayPaymentData($order)
            : null;
        // KIOT refusing the order no longer fails the checkout: the order is kept
        // and the shop confirms it by phone.
        $message = match ($order->kiot_sync_status) {
            'retrying' => 'Đơn hàng đã được ghi nhận và đang chờ hệ thống kho xác nhận.',
            'waiting_payment' => $order->payment_option === 'deposit'
                ? 'Đơn hàng đã được ghi nhận. Vui lòng chuyển khoản tiền cọc để shop xác nhận và giao hàng.'
                : 'Đơn hàng đã được ghi nhận. Vui lòng chuyển khoản để shop xác nhận và giao hàng.',
            'rejected', 'failed' => 'Đơn hàng đã được ghi nhận. Shop sẽ gọi điện xác nhận với bạn trước khi giao.',
            default => 'Đặt hàng thành công',
        };
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
            'deposited' => $order->payment_status === 'deposited',
            'payment_status' => $order->payment_status, 'order_status' => $order->order_status,
            'payment_option' => $order->payment_option, 'deposit_amount' => $order->deposit_amount,
            'amount_paid' => $order->amount_paid, 'remaining_amount' => $order->remainingAmount(),
            'kiot_sync_status' => $order->kiot_sync_status, 'kiot_order_code' => $order->kiot_order_code,
            'kiot_sync_error_code' => $order->kiot_sync_error_code,
            'can_pay' => $order->canPay(), 'can_cancel' => $order->canCancel(),
            // "awaiting_deposit" (Chờ cọc) / "awaiting_payment" (Chờ thanh toán) until SePay confirms the money.
            'payment_stage' => $order->paymentStage(), 'payment_stage_label' => $order->paymentStageLabel(),
            'payment' => $order->canPay() && $this->paymentAvailability->sepayAvailable()
                ? $this->generateSepayPaymentData($order)
                : null,
        ]);
    }

    /** SePay bank-transfer webhook; see SepayWebhookHandler. */
    public function sepayCallback(Request $request, SepayWebhookHandler $webhooks): JsonResponse
    {
        return $webhooks->handle($request);
    }

    public function cancel(Request $request, Order $order, KiotOrderCancellationService $cancellation): JsonResponse
    {
        $this->authorizeOrderAccess($request, $order);
        $validated = $request->validate(['reason' => 'nullable|string|max:500']);
        if (! $order->canCancel()) {
            return response()->json(['message' => 'Không thể hủy đơn hàng này'], 422);
        }

        try {
            $reason = $validated['reason'] ?? 'Khách hàng yêu cầu hủy';
            $order = OrderHistoryRecorder::actingAs('customer', $reason, fn () => $cancellation->cancel($order, $reason));
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
            'shipping_method' => $order->shipping_method ?: 'standard',
            'shipping_method_label' => ShippingCalculator::label($order->shipping_method),
            'total' => $order->total,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'payment_option' => $order->payment_option,
            'deposit_amount' => $order->deposit_amount,
            'amount_paid' => $order->amount_paid,
            'remaining_amount' => $order->remainingAmount(),
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
            'discount' => (int) $order->discount,
            'referral_code' => $order->referral_code,
            'referral_discount' => (int) $order->referral_discount,
            'created_at' => $order->created_at,
            'kiot_sync_status' => $order->kiot_sync_status,
            'kiot_order_code' => $order->kiot_order_code,
            'kiot_sync_error_code' => $order->kiot_sync_error_code,
            'can_pay' => $order->canPay(), 'can_cancel' => $order->canCancel(),
            // "awaiting_deposit" (Chờ cọc) / "awaiting_payment" (Chờ thanh toán) until SePay confirms the money.
            'payment_stage' => $order->paymentStage(), 'payment_stage_label' => $order->paymentStageLabel(),
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
                'warranty_months' => $item->warranty_months,
                'warranty_price' => (int) $item->warranty_price,
                'catalog_content_id' => $item->product?->catalogContentId($item->variant_id) ?: null,
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
        $cart = $userId
            ? Cart::where('user_id', $userId)
            : Cart::where('session_id', $request->header('X-Cart-Session') ?? session()->getId());
        $cart = $cart->first();
        if (! $cart) {
            return;
        }

        $cart->items()->where('is_selected', true)->delete();
        if (! $cart->items()->exists()) {
            $cart->delete();
        }
    }

    private function useSelectedCartItems(Request $request, array &$validated, bool $requireCart = false): void
    {
        if (($validated['checkout_mode'] ?? 'cart') !== 'cart') {
            return;
        }

        $userId = $this->authenticatedUserId($request);
        $cart = $userId
            ? Cart::where('user_id', $userId)->with('items')->first()
            : Cart::where('session_id', $request->header('X-Cart-Session') ?? session()->getId())->with('items')->first();
        if (! $cart) {
            if ($requireCart) {
                throw ValidationException::withMessages([
                    'items' => 'Giỏ hàng không còn sản phẩm được chọn. Vui lòng cập nhật lại giỏ hàng.',
                ]);
            }

            return;
        }

        $selected = $cart->items->where('is_selected', true)->values();
        if ($selected->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Vui lòng chọn ít nhất một sản phẩm để thanh toán.',
            ]);
        }

        $validated['items'] = $selected->map(fn (CartItem $item): array => [
            'product_id' => (int) $item->product_id,
            'variant_id' => $item->variant_id === null ? null : (int) $item->variant_id,
            'warranty_months' => $item->warranty_months === null ? null : (int) $item->warranty_months,
            'quantity' => (int) $item->quantity,
        ])->all();
    }

    /**
     * Store pickup: the order keeps the store address, so admin, KIOT and the
     * customer's order page all show where the goods are collected.
     */
    private function usePickupAddress(array &$validated, ShippingCalculator $shipping): void
    {
        $store = $shipping->pickupAddress();
        $validated['shipping_address'] = ShippingCalculator::label('pickup').($store ? ': '.$store : '');
        foreach (['shipping_city', 'shipping_district', 'shipping_ward', 'shipping_province_code', 'shipping_ward_code'] as $field) {
            $validated[$field] = null;
        }
    }

    private function resolveShippingLocation(array &$validated, LocationDirectory $locations): void
    {
        $provinceCode = trim((string) ($validated['shipping_province_code'] ?? ''));
        $wardCode = trim((string) ($validated['shipping_ward_code'] ?? ''));
        if (($provinceCode === '') !== ($wardCode === '')) {
            throw ValidationException::withMessages([
                'shipping_ward_code' => 'Vui lòng chọn đủ tỉnh/thành phố và xã/phường.',
            ]);
        }

        if ($provinceCode !== '' && $wardCode !== '') {
            $resolved = $locations->resolve($provinceCode, $wardCode);
            if ($resolved === null) {
                throw ValidationException::withMessages([
                    'shipping_ward_code' => 'Xã/phường không thuộc tỉnh/thành phố đã chọn.',
                ]);
            }
            $validated['shipping_city'] = $resolved['province']['fullname'];
            $validated['shipping_ward'] = $resolved['ward']['fullname'];
        }

        if (trim((string) ($validated['shipping_city'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'shipping_province_code' => 'Vui lòng chọn tỉnh/thành phố.',
            ]);
        }
    }

    private function generateSepayPaymentData(Order $order): array
    {
        $sepay = config('services.sepay');
        $bankCode = $this->stringSetting('payment_bank_name', (string) ($sepay['bank_code'] ?? ''));
        $bankAccount = $this->stringSetting('payment_bank_account', (string) ($sepay['bank_account'] ?? ''));
        $accountName = $this->stringSetting('payment_bank_holder', (string) ($sepay['account_name'] ?? ''));
        $amount = $order->amountDue();
        $purpose = match (true) {
            $order->payment_status === 'deposited' => 'remaining',
            $order->payment_option === 'deposit' && $amount < $order->remainingAmount() => 'deposit',
            default => 'full',
        };

        return [
            'qr_url' => "https://img.vietqr.io/image/{$bankCode}-{$bankAccount}-qr_only.png?amount={$amount}&addInfo=".urlencode($order->order_number).'&accountName='.urlencode($accountName),
            'bank_code' => $bankCode, 'bank_account' => $bankAccount,
            'account_name' => $accountName, 'amount' => $amount, 'purpose' => $purpose,
            'transfer_content' => $order->order_number, 'order_number' => $order->order_number,
        ];
    }

    private function stringSetting(string $key, string $fallback): string
    {
        $value = Setting::get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response
            ->header('Cache-Control', 'private, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
