<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = [
        'contact_hotline', 'contact_phone', 'contact_email', 'contact_address', 'business_hours',
        'social_zalo', 'social_facebook', 'social_youtube', 'social_tiktok', 'social_instagram',
    ];

    public function test_contact_and_social_settings_exist_and_are_public_without_seeding(): void
    {
        $rows = DB::table('settings')->whereIn('key', self::KEYS)->get()->keyBy('key');

        $this->assertEqualsCanonicalizing(self::KEYS, $rows->keys()->all());
        $this->assertTrue($rows->every(fn ($row) => (bool) $row->is_public && $row->value === ''));
        $this->assertSame('contact', $rows['contact_hotline']->group);
        $this->assertSame('social', $rows['social_zalo']->group);
    }

    public function test_rerunning_keeps_values_and_rollback_only_removes_empty_rows(): void
    {
        $migration = require database_path('migrations/2026_10_27_000001_add_missing_contact_and_social_settings.php');
        Setting::set('contact_hotline', '0909 000 111');

        $migration->up();
        $this->assertSame('0909 000 111', Setting::get('contact_hotline'));
        $this->assertSame(1, DB::table('settings')->where('key', 'contact_hotline')->count());

        $migration->down();
        $this->assertTrue(DB::table('settings')->where('key', 'contact_hotline')->exists());
        $this->assertFalse(DB::table('settings')->where('key', 'social_tiktok')->exists());
    }

    public function test_public_settings_api_exposes_hotline_for_the_storefront(): void
    {
        Setting::set('contact_hotline', '0909 000 111');
        Setting::set('social_zalo', '0909000111');

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('contact_hotline', '0909 000 111')
            ->assertJsonPath('social_zalo', '0909000111');
    }
}
