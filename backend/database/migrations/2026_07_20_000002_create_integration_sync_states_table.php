<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_sync_states', function (Blueprint $table) {
            $table->id();
            $table->string('integration');
            $table->string('resource');
            $table->string('status')->default('idle')->index();
            $table->text('last_cursor')->nullable();
            $table->timestamp('last_successful_watermark')->nullable();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_completed_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->unsignedInteger('items_processed')->default(0);
            $table->unsignedInteger('items_matched')->default(0);
            $table->unsignedInteger('items_unmatched')->default(0);
            $table->timestamps();
            $table->unique(['integration', 'resource']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_sync_states');
    }
};
