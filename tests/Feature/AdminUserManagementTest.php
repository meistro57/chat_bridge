<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_users_index_with_stats(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Users/Index')
            ->has('users', 4)
            ->has('stats')
            ->where('stats.total_users', 4)
            ->where('stats.admin_users', 1)
            ->has('filters')
        );
    }

    public function test_admin_can_search_users_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Alice Smith']);
        User::factory()->create(['name' => 'Bob Johnson']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'Alice']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Users/Index')
            ->has('users', 1)
            ->where('users.0.name', 'Alice Smith')
            ->where('filters.search', 'Alice')
        );
    }

    public function test_admin_can_search_users_by_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'alice@example.com']);
        User::factory()->create(['email' => 'bob@example.com']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'alice@']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('users', 1)
            ->where('users.0.email', 'alice@example.com')
        );
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create(['role' => 'user']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'admin']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('users', 1)
            ->where('users.0.role', 'admin')
        );
    }

    public function test_admin_can_filter_users_by_active_status(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['is_active' => true]);
        User::factory()->inactive()->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'inactive']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('users', 1)
            ->where('users.0.is_active', false)
        );
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertForbidden();
    }

    public function test_stats_show_correct_recent_users_count(): void
    {
        $admin = User::factory()->admin()->create();
        // Create a user from 10 days ago
        User::factory()->create(['created_at' => now()->subDays(10)]);
        // Create a user from today
        User::factory()->create(['created_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.recent_users', 2) // admin + today's user
        );
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
            'role' => 'user',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'role' => 'user',
        ]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
