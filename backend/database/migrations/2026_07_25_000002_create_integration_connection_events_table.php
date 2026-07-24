<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connection_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_connection_id')->constrained()->restrictOnDelete();
            $table->string('provider')->index();
            $table->string('event')->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['integration_connection_id', 'created_at'], 'ice_connection_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connection_events');
    }
};
