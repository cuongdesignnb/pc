<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Checkout\ShippingCalculator;

/**
 * The order as the shop's own tools see it: admin API (orders tokens) and the
 * outbound webhooks. Payment links, access tokens and KIOT internals are left out.
 */
class OrderApiPresenter
{
    /** @return array<string, mixed> */
    public function present(Order $order): array
    {
        $order->loadMissing(['items.product', 'affiliate', 'affiliateCommission']);

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
            // How the customer receives the order: standard, ghtk, ahamove or pickup.
            'shipping_method' => $order->shipping_method ?: 'standard',
            'shipping_method_label' => ShippingCalculator::label($order->shipping_method),
            'total' => (int) $order->total,
            'status' => $order->order_status,
            'status_label' => self::statusLabel((string) $order->order_status),
            'payment_status' => $order->payment_status,
            'payment_status_label' => self::paymentStatusLabel($order->payment_status),
            'payment_method' => $order->payment_method,
            'payment_option' => $order->payment_option,
            'deposit_amount' => (int) $order->deposit_amount,
            // "Chờ cọc"/"Chờ thanh toán": deposit required, SePay has not confirmed the money yet.
            'payment_stage' => $order->paymentStage(),
            'payment_stage_label' => $order->paymentStageLabel(),
            'amount_paid' => (int) $order->amount_paid,
            'paid_at' => $order->paid_at?->toIso8601String(),
            // Shipment, set by staff on the admin order page or with an orders_write token.
            'tracking_code' => $order->tracking_code,
            'shipping_carrier' => $order->shipping_carrier,
            'shipped_at' => $order->shipped_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'referral_code' => $order->referral_code,
            // Shopee/Lazada-style affiliate (CTV): who brought the customer and what they earn.
            'affiliate_id' => $order->affiliate_id,
            'affiliate_code' => $order->affiliate?->code,
            'referral_discount' => (int) $order->referral_discount,
            'affiliate_commission' => $order->affiliateCommission ? (int) $order->affiliateCommission->amount : null,
            'affiliate_commission_status' => $order->affiliateCommission?->status,
            'affiliate_commission_status_label' => self::commissionStatusLabel($order->affiliateCommission?->status),
            'affiliate_commission_approve_after' => $order->affiliateCommission?->approve_after?->toIso8601String(),
            // Where the order came from, as on the admin order page.
            'utm_source' => $order->utm_source,
            'utm_medium' => $order->utm_medium,
            'utm_campaign' => $order->utm_campaign,
            'utm_content' => $order->utm_content,
            'utm_term' => $order->utm_term,
            'gclid' => $order->gclid,
            'fbclid' => $order->fbclid,
            'video_code' => $order->video_code,
            'landing_page' => $order->landing_page,
            // Whether the order is in KIOT yet; "rejected"/"failed" orders are kept
            // pending on the website and resent (see the order's history).
            'kiot_sync_status' => $order->kiot_sync_status,
            'kiot_sync_status_label' => self::kiotStatusLabel($order->kiot_sync_status),
            'kiot_order_code' => $order->kiot_order_code,
            'kiot_error' => $order->kiot_sync_error_message,
            'kiot_auto_resend_count' => (int) $order->kiot_auto_resend_count,
            // Lines whose product exists only on the website (no KIOT code yet):
            // KIOT creates them without a cost price, so the invoice waits for one.
            'has_web_only_products' => $order->items->contains(fn (OrderItem $item) => $item->product && $item->product->isWebOnlyForKiot()),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'admin_url' => route('admin.orders.show', $order),
        ];
    }

    public static function kiotStatusLabel(?string $status): ?string
    {
        return match ($status) {
            null => null,
            'not_required' => 'Không cần đồng bộ',
            'waiting_payment' => 'Chờ cọc/thanh toán rồi mới gửi KIOT',
            'pending', 'retrying' => 'Đang gửi sang KIOT',
            'cancel_pending' => 'Đang huỷ bên KIOT',
            'deleted' => 'Đã xoá bên KIOT',
            'synced' => 'Đã vào KIOT',
            'rejected' => 'Chưa vào KIOT (KIOT từ chối)',
            'failed' => 'Chưa vào KIOT (lỗi kết nối)',
            'cancelled' => 'Đã huỷ bên KIOT',
            default => $status,
        };
    }

    public static function commissionStatusLabel(?string $status): ?string
    {
        return match ($status) {
            null => null,
            'pending' => 'Chờ duyệt',
            'approved' => 'Được rút',
            'cancelled' => 'Đã huỷ',
            default => $status,
        };
    }

    public static function paymentStatusLabel(?string $status): ?string
    {
        return match ($status) {
            null => null,
            'unpaid' => 'Chưa thanh toán',
            'deposited' => 'Đã đặt cọc',
            'paid' => 'Đã thanh toán',
            'refunded' => 'Đã hoàn tiền',
            default => $status,
        };
    }

    public static function statusLabel(string $status): string
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
}
