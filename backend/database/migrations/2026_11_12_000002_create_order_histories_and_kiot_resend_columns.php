<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - order_histories: every order status change (who/what, when, why) and
 *   every KIOT sync event (sent, refused, retried) on one timeline.
 * - orders.kiot_auto_resend_*: limited automatic resend of orders KIOT refused.
 * - products.kiot_code: KIOT goods code for a website-only product, sent to
 *   KIOT instead of the website SKU ("Gắn mã Kiot").
 * - Orders the website cancelled by itself when KIOT refused them (fixed now)
 *   go back to "pending" so the shop can call the customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_histories')) {
            Schema::create('order_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->string('type', 20)->default('status');
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30)->nullable();
                $table->string('actor_type', 20);
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_name', 150)->nullable();
                $table->string('message', 1000)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['order_id', 'id']);
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'kiot_auto_resend_count')) {
                $table->unsignedTinyInteger('kiot_auto_resend_count')->default(0)->after('kiot_sync_attempt_count');
                $table->timestamp('kiot_auto_resend_at')->nullable()->after('kiot_auto_resend_count');
            }
        });

        if (! Schema::hasColumn('products', 'kiot_code')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('kiot_code', 100)->nullable()->after('sku');
            });
        }

        $this->restoreAutoCancelledOrders();
    }

    private function restoreAutoCancelledOrders(): void
    {
        // Only the KIOT refusal path left an order "cancelled" with KIOT status
        // "rejected"; a cancel by staff or customer sets KIOT status "cancelled".
        $orders = DB::table('orders')
            ->where('order_status', 'cancelled')
            ->where('kiot_sync_status', 'rejected')
            ->get(['id', 'order_number']);

        foreach ($orders as $order) {
            DB::table('orders')->where('id', $order->id)->update(['order_status' => 'pending', 'updated_at' => now()]);
            DB::table('order_histories')->insert([
                'order_id' => $order->id,
                'type' => 'status',
                'from_status' => 'cancelled',
                'to_status' => 'pending',
                'actor_type' => 'system',
                'message' => 'Khôi phục đơn: trước đây web tự huỷ khi KIOT từ chối đơn. Đơn chưa vào KIOT, cần xử lý tay hoặc gửi lại.',
                'created_at' => now(),
            ]);
            if (Schema::hasTable('affiliate_commissions')) {
                DB::table('affiliate_commissions')
                    ->where('order_id', $order->id)
                    ->where('status', 'cancelled')
                    ->where('cancelled_reason', 'Đơn hàng đã huỷ')
                    ->update(['status' => 'pending', 'cancelled_reason' => null, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_histories');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['kiot_auto_resend_count', 'kiot_auto_resend_at']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('kiot_code'));
    }
};
