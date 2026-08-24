<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiGenerationScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_schedule_with_blank_optional_selects(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/admin/ai-writer/schedules', [
            'topic' => 'Hướng dẫn chọn laptop văn phòng',
            'keywords' => 'laptop văn phòng, pin, màn hình',
            'type' => 'article',
            'tone' => 'professional',
            'length' => 'medium',
            'full_article' => true,
            'with_images' => false,
            'image_count' => 0,
            'auto_publish' => false,
            'category_id' => '',
            'product_id' => '',
            'scheduled_at' => now()->addHour()->format('Y-m-d\TH:i'),
        ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('ai_generation_schedules', [
            'topic' => 'Hướng dẫn chọn laptop văn phòng',
            'type' => 'article',
            'category_id' => null,
            'product_id' => null,
            'status' => 'pending',
        ]);
    }

    public function test_product_description_schedule_requires_a_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/admin/ai-writer/schedules', [
                'topic' => 'Mô tả laptop',
                'type' => 'product_description',
                'tone' => 'professional',
                'length' => 'medium',
                'scheduled_at' => now()->addHour()->format('Y-m-d\TH:i'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Mô tả sản phẩm cần chọn sản phẩm.');
    }
}
