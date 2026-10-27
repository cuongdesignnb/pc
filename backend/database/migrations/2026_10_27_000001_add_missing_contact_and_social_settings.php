<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Contact and social settings used to exist only in SettingSeeder, which is
 * never run in production, so the admin had no hotline / Zalo / YouTube /
 * TikTok fields and the storefront hid the phone number everywhere. Create
 * any missing rows empty; existing rows and their values are left untouched.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'contact_hotline', 'group' => 'contact', 'type' => 'text', 'label' => 'Hotline (hiện trên header, footer và nút gọi)'],
        ['key' => 'contact_phone', 'group' => 'contact', 'type' => 'text', 'label' => 'Số điện thoại'],
        ['key' => 'contact_email', 'group' => 'contact', 'type' => 'text', 'label' => 'Email'],
        ['key' => 'contact_address', 'group' => 'contact', 'type' => 'textarea', 'label' => 'Địa chỉ cửa hàng'],
        ['key' => 'business_hours', 'group' => 'contact', 'type' => 'text', 'label' => 'Giờ làm việc'],
        ['key' => 'social_zalo', 'group' => 'social', 'type' => 'text', 'label' => 'Zalo (số điện thoại hoặc link zalo.me)'],
        ['key' => 'social_facebook', 'group' => 'social', 'type' => 'text', 'label' => 'Facebook'],
        ['key' => 'social_youtube', 'group' => 'social', 'type' => 'text', 'label' => 'YouTube'],
        ['key' => 'social_tiktok', 'group' => 'social', 'type' => 'text', 'label' => 'TikTok'],
        ['key' => 'social_instagram', 'group' => 'social', 'type' => 'text', 'label' => 'Instagram'],
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
    }

    public function down(): void
    {
        // Only remove rows that are still empty, so a rollback never deletes
        // contact details an administrator has already entered.
        DB::table('settings')
            ->whereIn('key', array_column(self::SETTINGS, 'key'))
            ->where(fn ($query) => $query->whereNull('value')->orWhere('value', ''))
            ->delete();
    }
};
