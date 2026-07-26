<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->index();
            $table->string('resource', 64)->index();
            $table->string('mode', 32)->index();
            $table->string('status', 32)->default('queued')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('cursor_before')->nullable();
            $table->text('cursor_after')->nullable();
            $table->json('totals_json')->nullable();
            $table->json('warnings_json')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('pages_processed')->default(0);
            $table->unsignedInteger('remote_processed')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('unchanged')->default(0);
            $table->unsignedInteger('images_downloaded')->default(0);
            $table->unsignedInteger('warnings')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->timestamps();
            $table->index(['provider', 'resource', 'created_at'], 'sync_runs_provider_resource_created_idx');
        });

        Schema::create('integration_sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('resource', 64);
            $table->string('conflict_type', 64);
            $table->unsignedBigInteger('remote_id');
            $table->string('sku')->nullable();
            $table->foreignId('local_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('status', 32)->default('open')->index();
            $table->json('details_json')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['provider', 'resource', 'conflict_type', 'remote_id'],
                'sync_conflicts_provider_remote_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_sync_conflicts');
        Schema::dropIfExists('integration_sync_runs');
    }
};
