<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Cart;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Get user's orders
     */
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'orders' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Get single order
     */
    public function show(Request $request, Order $order)
    {
        // Check ownership or guest order
        if ($order->user_id && $order->user_id !== $request->user()?->id) {
            abort(403, 'Unauthorized');
        }

        $order->load(['items.product', 'transaction']);

        return response()->json($order);
    }

    /**
     * Create new order
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
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
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Calculate totals
            $subtotal = 0;
            $orderItems = [];

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Check stock
                if ($product->stock_quantity < $item['quantity']) {
                    abort(422, "Sản phẩm {$product->name} không đủ số lượng (còn {$product->stock_quantity})");
                }

                $price = $product->sale_price ?? $product->price;
                $subtotal += $price * $item['quantity'];

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $price,
                ];
            }

            // Shipping fee (free for orders >= 500k)
            $shippingFee = $subtotal >= 500000 ? 0 : 30000;
            $total = $subtotal + $shippingFee;

            // Generate unique order number: DH + date + 4-digit random
            $orderNumber = Order::generateOrderNumber();

            // Create order - mapping to actual DB columns
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $request->user()?->id,
                'subtotal' => $subtotal,
                'discount' => 0,
                'shipping_fee' => $shippingFee,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'unpaid',
                'order_status' => 'pending',
                'shipping_name' => $validated['customer_name'],
                'shipping_phone' => $validated['customer_phone'],
                'customer_email' => $validated['customer_email'],
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'],
                'shipping_district' => $validated['shipping_district'] ?? null,
                'shipping_ward' => $validated['shipping_ward'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create order items & decrease stock
            foreach ($orderItems as $item) {
                $order->items()->create($item);
                Product::where('id', $item['product_id'])
                    ->decrement('stock_quantity', $item['quantity']);
            }

            // Clear cart
            if ($request->user()) {
                Cart::where('user_id', $request->user()->id)->delete();
            } else {
                Cart::where('session_id', session()->getId())->delete();
            }

            // Generate payment data for SePay
            $paymentData = null;
            if ($validated['payment_method'] === 'sepay') {
                $paymentData = $this->generateSepayPaymentData($order);
            }

            return response()->json([
                'message' => 'Đặt hàng thành công',
                'order' => $order->load('items.product'),
                'payment' => $paymentData,
            ], 201);
        });
    }

    /**
     * Generate SePay payment data (QR code URL + transfer info)
     */
    private function generateSepayPaymentData(Order $order): array
    {
        $sepay = config('services.sepay');
        $bankCode = $sepay['bank_code'];
        $bankAccount = $sepay['bank_account'];
        $accountName = $sepay['account_name'];

        // VietQR URL format for QR code image
        $qrUrl = "https://img.vietqr.io/image/{$bankCode}-{$bankAccount}-qr_only.png"
            . "?amount={$order->total}"
            . "&addInfo=" . urlencode($order->order_number)
            . "&accountName=" . urlencode($accountName);

        return [
            'qr_url' => $qrUrl,
            'bank_code' => $bankCode,
            'bank_account' => $bankAccount,
            'account_name' => $accountName,
            'amount' => (int) $order->total,
            'transfer_content' => $order->order_number,
            'order_number' => $order->order_number,
        ];
    }

    /**
     * Check payment status (polling from frontend)
     */
    public function checkPayment(Order $order)
    {
        return response()->json([
            'paid' => $order->payment_status === 'paid',
            'payment_status' => $order->payment_status,
            'order_status' => $order->order_status,
        ]);
    }

    /**
     * SePay webhook callback (IPN)
     * 
     * SePay sends transaction data when a payment is detected.
     * We match the order number from the transfer content and update status.
     */
    public function sepayCallback(Request $request)
    {
        // Log the full payload for debugging
        Log::channel('daily')->info('SePay IPN received', $request->all());

        // Verify webhook API key
        $webhookKey = config('services.sepay.webhook_key');
        $authHeader = $request->header('Authorization');

        if ($webhookKey && $authHeader !== "Apikey {$webhookKey}") {
            Log::warning('SePay IPN: Invalid API key', [
                'received' => $authHeader,
            ]);
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $content = $request->input('content', '');
        $transferAmount = $request->input('transferAmount', 0);
        $transactionId = $request->input('id');
        $gateway = $request->input('gateway', 'sepay');
        $referenceCode = $request->input('referenceNumber', '');
        $transactionDate = $request->input('transactionDate');

        // Extract order number from transfer content
        // Order number format: DH + YYYYMMDD + 4 digits (e.g., DH202602261234)
        if (!preg_match('/DH\d{12}/', $content, $matches)) {
            Log::info('SePay IPN: No order number found in content', ['content' => $content]);
            return response()->json(['success' => true, 'message' => 'No matching order']);
        }

        $orderNumber = $matches[0];

        $order = Order::where('order_number', $orderNumber)
            ->where('payment_status', 'unpaid')
            ->first();

        if (!$order) {
            Log::info('SePay IPN: Order not found or already paid', ['order_number' => $orderNumber]);
            return response()->json(['success' => true, 'message' => 'Order not found or already paid']);
        }

        // Verify amount matches
        if ((int) $transferAmount < (int) $order->total) {
            Log::warning('SePay IPN: Amount mismatch', [
                'order_number' => $orderNumber,
                'expected' => $order->total,
                'received' => $transferAmount,
            ]);
            return response()->json(['success' => true, 'message' => 'Amount mismatch']);
        }

        // Update order status
        $order->update([
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
            'paid_at' => now(),
        ]);

        // Create transaction record
        Transaction::create([
            'order_id' => $order->id,
            'sepay_transaction_id' => $transactionId,
            'gateway' => $gateway,
            'amount' => $transferAmount,
            'reference_code' => $referenceCode,
            'content' => $content,
            'transaction_date' => $transactionDate ? \Carbon\Carbon::parse($transactionDate) : now(),
        ]);

        Log::info('SePay IPN: Payment confirmed', [
            'order_number' => $orderNumber,
            'amount' => $transferAmount,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, Order $order)
    {
        // Check ownership
        if ($order->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        // Can only cancel pending/confirmed orders that are unpaid
        if (!in_array($order->order_status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Không thể hủy đơn hàng này',
            ], 422);
        }

        // Restore stock
        foreach ($order->items as $item) {
            Product::where('id', $item->product_id)
                ->increment('stock_quantity', $item->quantity);
        }

        $order->update([
            'order_status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Đơn hàng đã được hủy',
            'order' => $order->fresh(),
        ]);
    }
}
