<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'storefront_product_contact_footer_enabled', 'value' => '0', 'group' => 'contact', 'type' => 'boolean', 'label' => 'Footer liên hệ cuối nội dung sản phẩm', 'is_public' => true],
        ['key' => 'storefront_product_contact_footer_title', 'value' => '', 'group' => 'contact', 'type' => 'text', 'label' => 'Tiêu đề footer liên hệ sản phẩm', 'is_public' => true],
        ['key' => 'storefront_product_contact_footer_landmark', 'value' => '', 'group' => 'contact', 'type' => 'textarea', 'label' => 'Ghi chú vị trí cửa hàng', 'is_public' => true],
        ['key' => 'storefront_product_contact_footer_website_url', 'value' => '', 'group' => 'contact', 'type' => 'text', 'label' => 'Website trong footer sản phẩm', 'is_public' => true],
        ['key' => 'storefront_product_contact_footer_maps_url', 'value' => '', 'group' => 'contact', 'type' => 'text', 'label' => 'Google Maps trong footer sản phẩm', 'is_public' => true],
        ['key' => 'ai_product_research_enabled', 'value' => '0', 'group' => 'ai', 'type' => 'boolean', 'label' => 'Cho phép AI tra thông số sản phẩm trên web', 'is_public' => false],
        ['key' => 'ai_product_research_official_domains', 'value' => '', 'group' => 'ai', 'type' => 'textarea', 'label' => 'Domain chính hãng được phép tra cứu', 'is_public' => false],
        ['key' => 'ai_product_campaign_max_items', 'value' => '100', 'group' => 'ai', 'type' => 'number', 'label' => 'Số sản phẩm tối đa mỗi campaign AI', 'is_public' => false],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                [...$setting, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::SETTINGS, 'key'))->delete();
    }
};
