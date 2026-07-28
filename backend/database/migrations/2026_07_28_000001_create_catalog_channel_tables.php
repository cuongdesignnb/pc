<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_channel_connections', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32)->unique();
            $table->string('status', 32)->default('not_configured')->index();
            $table->boolean('is_enabled')->default(false)->index();
            $table->text('configuration_encrypted')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->text('last_error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('catalog_channel_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32)->index();
            $table->string('mode', 32)->index();
            $table->string('status', 32)->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('items_total')->default(0);
            $table->unsignedInteger('items_valid')->default(0);
            $table->unsignedInteger('items_invalid')->default(0);
            $table->unsignedInteger('items_created')->default(0);
            $table->unsignedInteger('items_updated')->default(0);
            $table->unsignedInteger('items_skipped')->default(0);
            $table->unsignedInteger('warnings')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->json('summary_json')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['channel', 'created_at'], 'catalog_runs_channel_created_idx');
        });

        Schema::create('catalog_channel_item_states', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32);
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('external_id', 255);
            $table->char('checksum', 64);
            $table->string('remote_row_id')->nullable();
            $table->string('remote_item_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status', 32)->index();
            $table->string('last_error_code', 64)->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();
            $table->unique(['channel', 'product_id'], 'catalog_states_channel_product_unique');
            $table->unique(['channel', 'external_id'], 'catalog_states_channel_external_unique');
        });

        Schema::create('catalog_channel_sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32)->index();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('external_id')->nullable()->index();
            $table->string('conflict_type', 64)->index();
            $table->json('details_json')->nullable();
            $table->string('status', 32)->default('open')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('catalog_channel_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_channel_connection_id')->constrained()->restrictOnDelete();
            $table->string('channel', 32)->index();
            $table->string('event', 64)->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['catalog_channel_connection_id', 'created_at'], 'catalog_events_connection_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_channel_events');
        Schema::dropIfExists('catalog_channel_sync_conflicts');
        Schema::dropIfExists('catalog_channel_item_states');
        Schema::dropIfExists('catalog_channel_sync_runs');
        Schema::dropIfExists('catalog_channel_connections');
    }
};
