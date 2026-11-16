<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactInboxTest extends TestCase
{
    use RefreshDatabase;

    private function message(array $attributes = []): ContactMessage
    {
        return ContactMessage::create($attributes + [
            'name' => 'Lê Văn An', 'phone' => '0901234567', 'email' => 'an@example.com',
            'subject' => 'Hỏi giá', 'message' => 'Shop còn Dell 7420 không?', 'status' => 'new',
        ]);
    }

    private function manager(): User
    {
        $user = User::factory()->create(['role' => 'staff']);
        $user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'contact-messages.view', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'contact-messages.manage', 'guard_name' => 'web']),
        ]);

        return $user;
    }

    public function test_migration_grants_the_inbox_to_roles_that_handle_orders_or_posts(): void
    {
        $migration = require database_path('migrations/2026_11_17_100003_add_contact_message_permissions.php');
        $staff = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $staff->givePermissionTo(Permission::firstOrCreate(['name' => 'orders.view', 'guard_name' => 'web']));
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $migration->up();

        $this->assertTrue($staff->fresh()->hasPermissionTo('contact-messages.view'));
        $this->assertTrue($admin->fresh()->hasPermissionTo('contact-messages.manage'));
        $this->assertFalse($viewer->fresh()->hasPermissionTo('contact-messages.view'));
    }

    public function test_inbox_lists_filters_and_searches(): void
    {
        $user = $this->manager();
        $this->message();
        $this->message(['name' => 'Phạm Bình', 'phone' => '0911222333', 'message' => 'Đổi màn hình', 'status' => 'read']);
        $this->message(['name' => 'Cũ', 'message' => 'Đã xử lý', 'status' => 'archived']);

        $this->actingAs($user)->get('/admin/contact-messages')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/ContactMessages/Index')
                ->has('messages.data', 2)
                ->where('counts.unread', 1)
                ->where('counts.archived', 1)
                ->where('canManage', true));

        $this->actingAs($user)->get('/admin/contact-messages?box=unread')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('messages.data', 1)->where('messages.data.0.name', 'Lê Văn An'));
        $this->actingAs($user)->get('/admin/contact-messages?box=archived')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('messages.data', 1)->where('messages.data.0.name', 'Cũ'));
        $this->actingAs($user)->get('/admin/contact-messages?box=all&search=0911222')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('messages.data', 1)->where('messages.data.0.name', 'Phạm Bình'));

        // The sidebar badge counts unread messages.
        $this->actingAs($user)->get('/admin/contact-messages')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('admin.contact_unread_count', 1));
    }

    public function test_open_marks_read_and_status_actions_work(): void
    {
        $user = $this->manager();
        $message = $this->message();

        $this->actingAs($user)->get("/admin/contact-messages/{$message->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/ContactMessages/Show')->where('message.status', 'read'));
        $this->assertSame('read', $message->fresh()->status);

        $this->actingAs($user)->patch("/admin/contact-messages/{$message->id}/status", ['status' => 'new'])->assertSessionHasNoErrors();
        $this->assertSame('new', $message->fresh()->status);
        $this->actingAs($user)->patch("/admin/contact-messages/{$message->id}/status", ['status' => 'archived'])->assertSessionHasNoErrors();
        $this->assertSame('archived', $message->fresh()->status);
        $this->actingAs($user)->patch("/admin/contact-messages/{$message->id}/status", ['status' => 'spam'])->assertSessionHasErrors('status');

        $this->actingAs($user)->delete("/admin/contact-messages/{$message->id}")->assertRedirect('/admin/contact-messages');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_staff_without_the_permission_cannot_open_the_inbox(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $viewer = User::factory()->create(['role' => 'staff']);
        $viewer->givePermissionTo(Permission::firstOrCreate(['name' => 'contact-messages.view', 'guard_name' => 'web']));
        Permission::firstOrCreate(['name' => 'contact-messages.manage', 'guard_name' => 'web']);
        $message = $this->message();

        $this->actingAs($user)->get('/admin/contact-messages')->assertForbidden();

        // View-only: reading does not change the status and actions are refused.
        $this->actingAs($viewer)->get("/admin/contact-messages/{$message->id}")->assertOk();
        $this->assertSame('new', $message->fresh()->status);
        $this->actingAs($viewer)->delete("/admin/contact-messages/{$message->id}")->assertForbidden();
    }
}
