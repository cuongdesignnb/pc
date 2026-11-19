<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaokimButtonSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_baokim_button_is_off_by_default_and_public(): void
    {
        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('baokim_button_enabled', false);
    }

    public function test_turning_the_button_on_reaches_the_storefront(): void
    {
        Setting::set('baokim_button_enabled', true, 'payment', 'boolean');

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('baokim_button_enabled', true);
    }
}
