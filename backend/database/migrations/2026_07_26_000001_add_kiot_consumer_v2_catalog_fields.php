<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('provider', 32)->nullable()->after('id');
            $table->unsignedBigInteger('remote_category_id')->nullable()->after('provider');
            $table->boolean('show_on_pc_website')->default(true)->after('is_active')->index();
            $table->string('provider_sync_status', 32)->nullable()->after('show_on_pc_website')->index();
            $table->char('provider_sync_checksum', 64)->nullable()->after('provider_sync_status');
            $table->timestamp('provider_updated_at')->nullable()->after('provider_sync_checksum');
            $table->timestamp('provider_synced_at')->nullable()->after('provider_updated_at')->index();
            $table->unique(['provider', 'remote_category_id'], 'categories_provider_remote_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('provider', 32)->nullable()->after('inventory_source');
            $table->unsignedBigInteger('remote_product_id')->nullable()->after('provider');
            $table->boolean('show_on_pc_website')->default(true)->after('is_active')->index();
            $table->string('kiot_availability_status', 32)->nullable()->after('kiot_sync_status')->index();
            $table->boolean('kiot_is_under_repair')->default(false)->after('kiot_availability_status');
            $table->decimal('kiot_selected_price', 15, 0)->nullable()->after('kiot_retail_price');
            $table->boolean('kiot_price_fallback_used')->default(false)->after('kiot_selected_price');
            $table->char('kiot_sync_checksum', 64)->nullable()->after('kiot_price_fallback_used');
            $table->unique(['provider', 'remote_product_id'], 'products_provider_remote_unique');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('provider', 32)->nullable()->after('product_id');
            $table->unsignedBigInteger('remote_image_id')->nullable()->after('provider');
            $table->text('source_url')->nullable()->after('url');
            $table->string('storage_path')->nullable()->after('source_url');
            $table->char('checksum', 64)->nullable()->after('storage_path');
            $table->string('mime_type', 64)->nullable()->after('checksum');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            $table->unsignedInteger('width')->nullable()->after('file_size');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->timestamp('synced_at')->nullable()->after('is_primary');
            $table->unique(['provider', 'remote_image_id'], 'product_images_provider_remote_unique');
            $table->index(['product_id', 'provider', 'checksum'], 'product_images_provider_checksum_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('product_images', 'product_images_product_id_foreign')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->index('product_id', 'product_images_product_id_foreign');
            });
        }

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropUnique('product_images_provider_remote_unique');
            $table->dropIndex('product_images_provider_checksum_idx');
            $table->dropColumn([
                'provider', 'remote_image_id', 'source_url', 'storage_path', 'checksum', 'mime_type',
                'file_size', 'width', 'height', 'synced_at',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_provider_remote_unique');
            $table->dropColumn([
                'provider', 'remote_product_id', 'show_on_pc_website', 'kiot_availability_status',
                'kiot_is_under_repair', 'kiot_selected_price', 'kiot_price_fallback_used',
                'kiot_sync_checksum',
            ]);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_provider_remote_unique');
            $table->dropColumn([
                'provider', 'remote_category_id', 'show_on_pc_website', 'provider_sync_status',
                'provider_sync_checksum', 'provider_updated_at', 'provider_synced_at',
            ]);
        });
    }
};
