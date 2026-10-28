<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeoSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = [
        'site_name', 'site_tagline', 'site_description', 'seo_title', 'seo_description',
        'seo_keywords', 'seo_og_image', 'google_analytics_id', 'google_tag_manager_id', 'facebook_pixel_id',
    ];

    public function test_seo_and_tracking_settings_exist_and_are_public_without_seeding(): void
    {
        $rows = DB::table('settings')->whereIn('key', self::KEYS)->get()->keyBy('key');

        $this->assertEqualsCanonicalizing(self::KEYS, $rows->keys()->all());
        $this->assertTrue($rows->every(fn ($row) => (bool) $row->is_public));
        $this->assertSame('seo', $rows['google_analytics_id']->group);
        $this->assertSame('general', $rows['site_name']->group);
    }

    public function test_rerunning_keeps_values_and_rollback_only_removes_empty_rows(): void
    {
        $migration = require database_path('migrations/2026_10_28_000001_add_missing_seo_and_tracking_settings.php');
        Setting::set('seo_title', 'Laptop Plus - Laptop cũ, mới uy tín');

        $migration->up();
        $this->assertSame('Laptop Plus - Laptop cũ, mới uy tín', Setting::get('seo_title'));
        $this->assertSame(1, DB::table('settings')->where('key', 'seo_title')->count());

        $migration->down();
        $this->assertTrue(DB::table('settings')->where('key', 'seo_title')->exists());
        $this->assertFalse(DB::table('settings')->where('key', 'google_tag_manager_id')->exists());
    }

    public function test_public_settings_api_exposes_seo_title_and_ga4_id(): void
    {
        Setting::set('seo_title', 'Laptop Plus');
        Setting::set('google_analytics_id', 'G-ABC1234567');

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('seo_title', 'Laptop Plus')
            ->assertJsonPath('google_analytics_id', 'G-ABC1234567');
    }
}
