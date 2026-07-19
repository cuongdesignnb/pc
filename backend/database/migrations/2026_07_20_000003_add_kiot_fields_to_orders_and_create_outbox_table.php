<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('checkout_idempotency_key')->nullable()->unique();
            $table->uuid('kiot_event_id')->nullable()->unique();
            $table->uuid('kiot_idempotency_key')->nullable()->unique();
            $table->unsignedBigInteger('kiot_order_id')->nullable()->index();
            $table->string('kiot_order_code')->nullable()->index();
            $table->string('kiot_sync_status')->default('not_required')->index();
            $table->unsignedInteger('kiot_sync_attempt_count')->default(0);
            $table->string('kiot_payload_hash', 64)->nullable();
            $table->timestamp('kiot_last_attempt_at')->nullable();
            $table->timestamp('kiot_synced_at')->nullable();
            $table->string('kiot_sync_error_code')->nullable();
            $table->text('kiot_sync_error_message')->nullable();
            $table->json('kiot_response')->nullable();
        });

        Schema::create('integration_outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('integration')->index();
            $table->string('event_type')->index();
            $table->string('aggregate_type');
            $table->unsignedBigInteger('aggregate_id')->index();
            $table->uuid('event_id');
            $table->uuid('idempotency_key');
            $table->json('payload');
            $table->longText('raw_body');
            $table->string('payload_hash', 64);
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('locked_at')->nullable()->index();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['integration', 'event_id']);
            $table->unique(['integration', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_outbox_events');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'checkout_idempotency_key', 'kiot_event_id', 'kiot_idempotency_key',
                'kiot_order_id', 'kiot_order_code', 'kiot_sync_status',
                'kiot_sync_attempt_count', 'kiot_payload_hash', 'kiot_last_attempt_at',
                'kiot_synced_at', 'kiot_sync_error_code', 'kiot_sync_error_message',
                'kiot_response',
            ]);
        });
    }
};
