<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('configuration_source');
            $table->text('base_url')->nullable();
            $table->string('client_id')->nullable();
            $table->text('secret_encrypted')->nullable();
            $table->string('secret_fingerprint', 16)->nullable();
            $table->string('api_version', 32)->default('v1');
            $table->string('connection_status')->default('unconfigured')->index();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('product_sync_enabled')->default(false);
            $table->boolean('order_sync_enabled')->default(false);
            $table->json('capabilities')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};
