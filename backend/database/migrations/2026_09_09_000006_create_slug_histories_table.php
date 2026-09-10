<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slug_histories', function (Blueprint $table): void {
            $table->id();
            $table->string('site_key', 32)->default('storefront');
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');
            $table->string('route_namespace', 64);
            // Keep the original value intact so an old, longer slug can still
            // be redirected after the canonical slug is shortened.
            $table->string('legacy_slug', 255)->nullable();
            $table->string('source_path', 512);
            $table->string('target_path', 512);
            $table->unsignedBigInteger('target_entity_id')->nullable();
            $table->unsignedSmallInteger('redirect_status')->default(301);
            $table->string('migration_batch_id', 64)->nullable();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();

            $table->unique(['site_key', 'source_path']);
            $table->index(['route_namespace', 'legacy_slug']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('target_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_histories');
    }
};
