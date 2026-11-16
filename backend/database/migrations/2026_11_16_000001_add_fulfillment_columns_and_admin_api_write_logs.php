<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order write API (status, payment, shipment by admin API token) and the
 * automatic KIOT invoice/receipt: tracking info on the order, what has been
 * sent to KIOT already, and a log of every write made with a token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'tracking_code')) {
                $table->string('tracking_code', 100)->nullable();
            }
            if (! Schema::hasColumn('orders', 'shipping_carrier')) {
                $table->string('shipping_carrier', 100)->nullable();
            }
            if (! Schema::hasColumn('orders', 'kiot_paid_synced_amount')) {
                $table->decimal('kiot_paid_synced_amount', 15, 0)->default(0);
            }
            if (! Schema::hasColumn('orders', 'kiot_delivery_synced_at')) {
                $table->timestamp('kiot_delivery_synced_at')->nullable();
            }
        });

        if (! Schema::hasTable('admin_api_write_logs')) {
            Schema::create('admin_api_write_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('token_id')->nullable()->index();
                $table->string('token_name')->nullable();
                $table->string('action', 50);
                $table->string('target_type', 50)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('idempotency_key', 100)->nullable();
                $table->char('request_hash', 64);
                $table->json('request')->nullable();
                $table->unsignedSmallInteger('status_code')->default(0);
                $table->json('response')->nullable();
                $table->string('error_code', 100)->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->nullable()->index();
                $table->unique(['token_id', 'idempotency_key']);
                $table->index(['target_type', 'target_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_api_write_logs');
        Schema::table('orders', function (Blueprint $table) {
            foreach (['tracking_code', 'shipping_carrier', 'kiot_paid_synced_amount', 'kiot_delivery_synced_at'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
