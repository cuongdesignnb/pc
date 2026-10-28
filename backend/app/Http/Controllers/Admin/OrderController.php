<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\KiotIntegrationException;
use App\Http\Controllers\Controller;
use App\Jobs\Integrations\Kiot\ProcessKiotOutboxEvent;
use App\Models\Order;
use App\Services\Integrations\Kiot\KiotConfigurationResolver;
use App\Services\Integrations\Kiot\KiotOrderCancellationService;
use App\Services\Integrations\Kiot\KiotOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'items'])
            ->latest();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('order_number', 'like', "%{$request->search}%")
                    ->orWhere('shipping_name', 'like', "%{$request->search}%")
                    ->orWhere('shipping_phone', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('order_status', $request->status);
        }

        if ($request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $orders = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status', 'payment_status', 'from_date', 'to_date']),
            'statuses' => ['pending', 'confirmed', 'processing', 'shipping', 'delivered', 'cancelled'],
            'paymentStatuses' => ['unpaid', 'deposited', 'paid', 'refunded'],
        ]);
    }

    public function show(Order $order, KiotConfigurationResolver $resolver)
    {
        $order->load(['user', 'items.product', 'transactions']);
        $runtime = $resolver->resolve();
        $orderSyncEnabled = $runtime->enabled && $runtime->orderSyncEnabled;

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
            'kiot' => [
                'status' => $order->kiot_sync_status,
                'order_code' => $order->kiot_order_code,
                'error_message' => $order->kiot_sync_error_message,
                'order_sync_enabled' => $orderSyncEnabled,
                'can_resend' => $orderSyncEnabled && $order->canResendToKiot(),
            ],
        ]);
    }

    public function resendToKiot(Order $order, KiotOrderService $kiotOrders)
    {
        try {
            $eventId = $kiotOrders->resend($order);
        } catch (KiotIntegrationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        // Send right away so the admin sees the result; if KIOT is slow or
        // down the event stays queued and kiot:retry-outbox keeps trying.
        ProcessKiotOutboxEvent::dispatchSync($eventId);
        $order->refresh();

        return match ($order->kiot_sync_status) {
            'synced' => back()->with('success', 'Đã gửi đơn sang KIOT'.($order->kiot_order_code ? " (mã {$order->kiot_order_code})." : '.')),
            'rejected', 'failed' => back()->with('error', 'KIOT từ chối đơn: '.($order->kiot_sync_error_message ?: 'không rõ lý do')),
            default => back()->with('success', 'KIOT chưa phản hồi, hệ thống sẽ tự gửi lại trong ít phút.'),
        };
    }

    public function updateStatus(Request $request, Order $order, KiotOrderCancellationService $cancellation)
    {
        $validated = $request->validate([
            'order_status' => 'required|in:pending,confirmed,processing,shipping,delivered,cancelled',
        ]);

        if ($validated['order_status'] === 'cancelled') {
            try {
                $cancellation->cancel($order, 'Admin yêu cầu hủy đơn');
            } catch (KiotIntegrationException $exception) {
                return back()->with('error', in_array($exception->errorCode, ['ORDER_ALREADY_INVOICED', 'ORDER_NOT_CANCELLABLE'], true)
                    ? 'Đơn hàng đã được xử lý trong hệ thống kho và không thể hủy.'
                    : $exception->getMessage());
            }
        } else {
            $order->update($validated);
        }

        return back()->with('success', 'Cập nhật trạng thái đơn hàng thành công');
    }

    public function updatePaymentStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'payment_status' => 'required|in:unpaid,deposited,paid,refunded',
        ]);

        if (in_array($validated['payment_status'], ['paid', 'deposited'], true)
            && $order->payment_method === 'sepay'
            && ! $order->acceptsPayments()) {
            return back()->with('error', 'Chỉ có thể xác nhận thanh toán sau khi KIOT chấp nhận đơn hàng.');
        }

        $data = $validated;
        if ($validated['payment_status'] === 'paid') {
            // Marked paid by hand (cash on delivery, transfer outside SePay):
            // the whole order is now settled.
            $data['paid_at'] = $order->paid_at ?? now();
            $data['amount_paid'] = max((int) $order->amount_paid, (int) $order->total);
        }
        if ($validated['payment_status'] === 'deposited' && ! $order->deposit_paid_at) {
            $data['deposit_paid_at'] = now();
        }

        $order->update($data);

        return back()->with('success', 'Cập nhật trạng thái thanh toán thành công');
    }
}
