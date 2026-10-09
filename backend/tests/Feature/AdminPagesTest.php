<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_empty_index_returns_an_array_without_a_pagination_wrapper(): void
    {
        $this->get('/admin/pages', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Pages/Index')
            ->assertJsonPath('props.pages', []);
    }

    public function test_index_returns_all_pages_including_hidden_pages_in_latest_order(): void
    {
        $older = Page::create($this->fixture('trang-cu'));
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $hidden = Page::create($this->fixture('trang-an'));
        $published = Page::create(array_replace($this->fixture('trang-hien'), [
            'is_active' => true,
        ]));
        $published->forceFill(['created_at' => now()->subHour()])->save();

        $response = $this->get('/admin/pages', ['X-Inertia' => 'true'])->assertOk();
        $pages = $response->json('props.pages');

        $this->assertIsArray($pages);
        $this->assertTrue(array_is_list($pages));
        $this->assertSame([$hidden->id, $published->id, $older->id], array_column($pages, 'id'));
        $this->assertSame($hidden->title, $pages[0]['title']);
        $this->assertSame($hidden->slug, $pages[0]['slug']);
        $this->assertFalse($pages[0]['is_active']);
        $this->assertTrue($pages[1]['is_active']);
        $this->assertFalse(Page::active()->whereKey($hidden->id)->exists());
        $this->assertFalse($hidden->fresh()->is_active);
    }

    public function test_a_hidden_page_is_saved_and_survives_redirect_and_reload_unchanged(): void
    {
        $fixture = $this->fixture();
        $this->get('/admin/pages/create')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Pages/Create'));

        $this->post('/admin/pages', $fixture)
            ->assertRedirect('/admin/pages')
            ->assertSessionHas('success', 'Tạo trang thành công');
        $this->assertDatabaseCount('pages', 1);
        $this->assertDatabaseHas('pages', $fixture);
        $saved = Page::sole();

        $this->get('/admin/pages', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.flash.success', 'Tạo trang thành công')
            ->assertJsonPath('props.pages.0.id', $saved->id);

        $this->get('/admin/pages', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.flash.success', null)
            ->assertJsonPath('props.pages.0.id', $saved->id)
            ->assertJsonPath('props.pages.0.title', $fixture['title'])
            ->assertJsonPath('props.pages.0.slug', $fixture['slug'])
            ->assertJsonPath('props.pages.0.body', $fixture['body'])
            ->assertJsonPath('props.pages.0.is_active', false);
        $this->assertDatabaseHas('pages', ['id' => $saved->id] + $fixture);
    }

    public function test_required_fields_fail_without_creating_a_page_or_success_flash(): void
    {
        $this->from('/admin/pages/create')->post('/admin/pages', [
            'title' => '', 'slug' => '', 'body' => '', 'is_active' => false,
        ])->assertRedirect('/admin/pages/create')
            ->assertSessionHasErrors(['title', 'slug', 'body'])
            ->assertSessionMissing('success');

        $this->assertDatabaseCount('pages', 0);
    }

    public function test_duplicate_slug_does_not_create_a_record_or_show_old_success(): void
    {
        $fixture = $this->fixture();
        $this->post('/admin/pages', $fixture)->assertRedirect('/admin/pages');
        $original = Page::sole();
        $this->get('/admin/pages', ['X-Inertia' => 'true'])
            ->assertJsonPath('props.flash.success', 'Tạo trang thành công');
        $this->get('/admin/pages/create')->assertOk();

        $this->from('/admin/pages/create')
            ->post('/admin/pages', array_replace($fixture, ['title' => 'Trang thử bị trùng']))
            ->assertRedirect('/admin/pages/create')
            ->assertSessionHasErrors('slug')
            ->assertSessionMissing('success');

        $this->assertDatabaseCount('pages', 1);
        $this->assertDatabaseHas('pages', ['id' => $original->id] + $fixture);
        $this->get('/admin/pages', ['X-Inertia' => 'true'])
            ->assertJsonPath('props.flash.success', null)
            ->assertJsonPath('props.pages.0.id', $original->id);
    }

    public function test_a_simulated_save_exception_does_not_create_a_record_or_success_flash(): void
    {
        // Fail before INSERT using an Eloquent event; do not damage the database.
        Page::creating(function (): void {
            throw new RuntimeException('Simulated page save failure.');
        });

        $this->post('/admin/pages', $this->fixture())
            ->assertStatus(500)
            ->assertSessionMissing('success');
        $this->assertDatabaseCount('pages', 0);
    }

    public function test_editing_a_hidden_page_preserves_its_id_slug_and_visibility_on_reload(): void
    {
        $page = Page::create($this->fixture());
        $this->get('/admin/pages/'.$page->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('Admin/Pages/Edit')
                ->where('page.id', $page->id)
                ->where('page.slug', $page->slug)
                ->where('page.is_active', false));

        $updated = array_replace($this->fixture(), [
            'title' => 'Trang thử nghiệm đã sửa',
            'body' => '<p>Nội dung giả đã cập nhật.</p>',
        ]);
        $this->put('/admin/pages/'.$page->id, $updated)
            ->assertRedirect('/admin/pages')
            ->assertSessionHas('success', 'Cập nhật trang thành công');
        $this->get('/admin/pages', ['X-Inertia' => 'true'])
            ->assertJsonPath('props.flash.success', 'Cập nhật trang thành công');
        $this->get('/admin/pages', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.flash.success', null)
            ->assertJsonPath('props.pages.0.id', $page->id)
            ->assertJsonPath('props.pages.0.title', $updated['title'])
            ->assertJsonPath('props.pages.0.slug', $page->slug)
            ->assertJsonPath('props.pages.0.body', $updated['body'])
            ->assertJsonPath('props.pages.0.is_active', false);
        $this->assertDatabaseCount('pages', 1);
        $this->assertDatabaseHas('pages', ['id' => $page->id] + $updated);
        $this->assertFalse(Page::active()->whereKey($page->id)->exists());
    }

    private function fixture(string $slug = 'trang-thu-nghiem-danh-sach'): array
    {
        return [
            'title' => 'Trang thử nghiệm danh sách',
            'slug' => $slug,
            'body' => '<p>Nội dung giả chỉ dùng trong test.</p>',
            'meta_title' => 'Tiêu đề SEO thử nghiệm',
            'meta_description' => 'Mô tả thử nghiệm.',
            'is_active' => false,
        ];
    }
}
