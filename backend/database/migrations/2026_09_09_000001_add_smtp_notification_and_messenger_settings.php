<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();
        foreach ($this->settings() as $setting) {
            DB::table('settings')->insertOrIgnore($setting + [
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column($this->settings(), 'key'))->delete();
    }

    /** @return array<int, array<string, mixed>> */
    private function settings(): array
    {
        return [
            [
                'key' => 'social_messenger',
                'value' => '',
                'group' => 'social',
                'type' => 'text',
                'label' => 'Messenger (URL hoặc page ID)',
                'options' => null,
                'is_public' => true,
            ],
            [
                'key' => 'smtp_enabled',
                'value' => '0',
                'group' => 'smtp',
                'type' => 'boolean',
                'label' => 'Bật gửi email SMTP',
                'options' => null,
                'is_public' => false,
            ],
            [
                'key' => 'smtp_host',
                'value' => '',
                'group' => 'smtp',
                'type' => 'text',
                'label' => 'Máy chủ SMTP',
                'options' => null,
                'is_public' => false,
            ],
            [
                'key' => 'smtp_port',
                'value' => '587',
                'group' => 'smtp',
                'type' => 'number',
                'label' => 'Cổng SMTP',
                'options' => null,
                'is_public' => false,
            ],
            [
                'key' => 'smtp_encryption',
                'value' => 'tls',
                'group' => 'smtp',
                'type' => 'select',
                'label' => 'Bảo mật kết nối',
                'options' => json_encode(['choices' => ['tls', 'ssl', 'none']]),
                'is_public' => false,
            ],
            [
                'key' => 'smtp_username',
                'value' => '',
                'group' => 'smtp',
                'type' => 'text',
                'label' => 'Tên đăng nhập SMTP',
                'options' => null,
                'is_public' => false,
            ],
            [
                'key' => 'smtp_password',
                'value' => '',
                'group' => 'smtp',
                'type' => 'password',
                'label' => 'Mật khẩu SMTP',
                'options' => null,
                'is_public' => false,
            ],
            [
                'key' => 'smtp_from_address',
                'value' => '',
                'group' => 'smtp',
                'type' => 'text',
                'label' => 'Email người gửi',
                'options' => null,
                'is_public' => false,
            ],
            [
                'key' => 'smtp_from_name',
                'value' => '',
                'group' => 'smtp',
                'type' => 'text',
                'label' => 'Tên người gửi',
                'options' => null,
                'is_public' => false,
            ],
            [
                'key' => 'smtp_order_recipient',
                'value' => '',
                'group' => 'smtp',
                'type' => 'text',
                'label' => 'Email nhận thông báo đơn hàng',
                'options' => null,
                'is_public' => false,
            ],
            [
                'key' => 'smtp_order_notifications_enabled',
                'value' => '0',
                'group' => 'smtp',
                'type' => 'boolean',
                'label' => 'Gửi email khi có đơn hàng mới',
                'options' => null,
                'is_public' => false,
            ],
        ];
    }
};
