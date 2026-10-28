<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Site identity, SEO and tracking settings used to exist only in
 * SettingSeeder, which is never run in production, so the admin had no
 * fields for the homepage title/description or the GA4 / GTM / Pixel IDs.
 * Create any missing rows empty; existing rows and their values are kept.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'site_name', 'group' => 'general', 'type' => 'text', 'label' => 'Tên website'],
        ['key' => 'site_tagline', 'group' => 'general', 'type' => 'text', 'label' => 'Slogan'],
        ['key' => 'site_description', 'group' => 'general', 'type' => 'textarea', 'label' => 'Mô tả website'],
        ['key' => 'seo_title', 'group' => 'seo', 'type' => 'text', 'label' => 'Tiêu đề trang chủ (SEO title)'],
        ['key' => 'seo_description', 'group' => 'seo', 'type' => 'textarea', 'label' => 'Mô tả trang chủ (SEO description)'],
        ['key' => 'seo_keywords', 'group' => 'seo', 'type' => 'text', 'label' => 'SEO Keywords mặc định'],
        ['key' => 'seo_og_image', 'group' => 'seo', 'type' => 'image', 'label' => 'Ảnh chia sẻ mặc định (OG image)'],
        ['key' => 'google_analytics_id', 'group' => 'seo', 'type' => 'text', 'label' => 'Google Analytics 4 (mã G-XXXXXXX)'],
        ['key' => 'google_tag_manager_id', 'group' => 'seo', 'type' => 'text', 'label' => 'Google Tag Manager (mã GTM-XXXXXX)'],
        ['key' => 'facebook_pixel_id', 'group' => 'seo', 'type' => 'text', 'label' => 'Facebook Pixel ID'],
        ['key' => 'google_site_verification', 'group' => 'seo', 'type' => 'text', 'label' => 'Mã xác minh Google Search Console (phần content của thẻ meta)'],
        ['key' => 'homepage_h1', 'group' => 'seo', 'type' => 'text', 'label' => 'Tiêu đề H1 trang chủ'],
        ['key' => 'business_schema_type', 'group' => 'seo', 'type' => 'text', 'label' => 'Loại hình cửa hàng cho schema (vd: ComputerStore, ElectronicsStore)', 'value' => 'ComputerStore'],
        ['key' => 'business_opening_hours', 'group' => 'contact', 'type' => 'text', 'label' => 'Giờ mở cửa cho Google (vd: Mo-Su 08:00-20:00)'],
        ['key' => 'contact_maps_url', 'group' => 'contact', 'type' => 'text', 'label' => 'Link Google Maps của cửa hàng'],
        ['key' => 'llms_txt', 'group' => 'seo', 'type' => 'textarea', 'label' => 'Nội dung file llms.txt (để trống thì /llms.txt trả 404)', 'is_public' => false],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as $setting) {
            if (DB::table('settings')->where('key', $setting['key'])->exists()) {
                continue;
            }
            DB::table('settings')->insert($setting + [
                'value' => '',
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Cache::forget('settings.public');
    }

    public function down(): void
    {
        // Only remove rows that are still empty, so a rollback never deletes
        // values an administrator has already entered.
        DB::table('settings')
            ->whereIn('key', array_column(self::SETTINGS, 'key'))
            ->where(fn ($query) => $query->whereNull('value')->orWhere('value', ''))
            ->delete();
        Cache::forget('settings.public');
    }
};
