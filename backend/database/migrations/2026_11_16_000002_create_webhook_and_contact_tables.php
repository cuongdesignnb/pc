<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound webhooks (to the shop's hook.laptopplus.vn) and the storefront
 * contact form. The webhook switch starts off and has no URL or secret.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webhook_endpoints')) {
            Schema::create('webhook_endpoints', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(false);
                $table->string('url', 500)->nullable();
                $table->text('secret')->nullable();
                $table->json('events')->nullable();
                $table->timestamp('secret_rotated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('webhook_deliveries')) {
            Schema::create('webhook_deliveries', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('event', 50)->index();
                $table->json('payload');
                $table->string('status', 20)->default('pending')->index();
                $table->unsignedInteger('attempt_count')->default(0);
                $table->timestamp('next_attempt_at')->nullable()->index();
                $table->timestamp('locked_until')->nullable();
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contact_messages')) {
            Schema::create('contact_messages', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('phone', 30)->nullable();
                $table->string('email', 191)->nullable();
                $table->string('subject', 200)->nullable();
                $table->text('message');
                $table->string('page_url', 500)->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->string('status', 20)->default('new')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
