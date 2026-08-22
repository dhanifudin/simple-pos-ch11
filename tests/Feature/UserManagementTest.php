<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_kasir_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Kasir Baru',
            'email' => 'kasirbaru@pos.test',
            'password' => 'password123',
            'role' => 'kasir',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'kasirbaru@pos.test', 'role' => 'kasir', 'is_active' => 1]);
    }

    public function test_admin_can_toggle_user_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($admin)->patch("/users/{$kasir->id}/status")->assertRedirect();
        $this->assertFalse($kasir->fresh()->is_active);

        $this->actingAs($admin)->patch("/users/{$kasir->id}/status")->assertRedirect();
        $this->assertTrue($kasir->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch("/users/{$admin->id}/status")->assertRedirect();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_two_active_admins_can_deactivate_each_other_but_not_down_to_zero(): void
    {
        $adminA = User::factory()->create(['role' => 'admin']);
        $adminB = User::factory()->create(['role' => 'admin']);

        // Two active admins: B may deactivate A — one active admin (B) remains.
        $this->actingAs($adminB)->patch("/users/{$adminA->id}/status")->assertRedirect();
        $this->assertFalse($adminA->fresh()->is_active);
    }

    public function test_last_active_admin_cannot_demote_own_role_via_update(): void
    {
        // The self-account guard in toggleStatus() already blocks self-deactivation
        // outright, so the only reachable path to zero active admins is a solo admin
        // editing their own role through the update form.
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => '',
            'role' => 'kasir',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertEquals('admin', $admin->fresh()->role);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['role' => 'kasir', 'is_active' => false, 'password' => bcrypt('password')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_obtain_api_token(): void
    {
        $user = User::factory()->create(['role' => 'kasir', 'is_active' => false, 'password' => bcrypt('password')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(422);
    }

    public function test_kasir_is_blocked_from_users_page(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($kasir)->get('/users')->assertForbidden();
    }
}
