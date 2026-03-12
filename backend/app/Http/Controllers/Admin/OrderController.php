<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
            'paymentStatuses' => ['unpaid', 'paid', 'refunded'],
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['user', 'items.product', 'transaction']);

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'order_status' => 'required|in:pending,confirmed,processing,shipping,delivered,cancelled',
        ]);

        $order->update($validated);

        return back()->with('success', 'Cập nhật trạng thái đơn hàng thành công');
    }

    public function updatePaymentStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'payment_status' => 'required|in:unpaid,paid,refunded',
        ]);

        $data = $validated;
        if ($validated['payment_status'] === 'paid' && !$order->paid_at) {
            $data['paid_at'] = now();
        }

        $order->update($data);

        return back()->with('success', 'Cập nhật trạng thái thanh toán thành công');
    }
}
