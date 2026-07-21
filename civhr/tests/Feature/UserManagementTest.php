<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function withRoles(User $user, array $roles): User
    {
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user->fresh();
    }

    public function test_an_admin_can_update_a_user_and_roles(): void
    {
        $admin = $this->withRoles(User::factory()->create(), ['superadmin']);
        $target = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($admin)->patch(route('admin.users.update', $target), [
            'name'  => 'New Name',
            'email' => 'new@example.com',
            'roles' => Role::whereIn('name', ['admin', 'employee'])->pluck('id')->all(),
        ])->assertRedirect();

        $target->refresh();
        $this->assertSame('New Name', $target->name);
        $this->assertSame('new@example.com', $target->email);
        $this->assertEqualsCanonicalizing(['admin', 'employee'], $target->roles->pluck('name')->all());
    }

    public function test_an_admin_can_reset_a_password(): void
    {
        $admin = $this->withRoles(User::factory()->create(), ['admin']);
        $target = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.password', $target), [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('new-password-123', $target->fresh()->password));
    }

    public function test_an_admin_can_deactivate_a_user(): void
    {
        $admin = $this->withRoles(User::factory()->create(), ['admin']);
        $target = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.users.toggle', $target))->assertRedirect();
        $this->assertFalse((bool) $target->fresh()->is_active);
    }

    public function test_a_deactivated_user_cannot_log_in(): void
    {
        // The 'hashed' cast hashes this on save.
        $target = User::factory()->create(['is_active' => false, 'password' => 'secret123']);

        $this->post(route('login'), ['email' => $target->email, 'password' => 'secret123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->withRoles(User::factory()->create(), ['admin']);

        $this->actingAs($admin)->patch(route('admin.users.toggle', $admin))->assertForbidden();
        $this->assertTrue((bool) $admin->fresh()->is_active);
    }

    public function test_a_non_admin_cannot_manage_users(): void
    {
        $employee = $this->withRoles(User::factory()->create(), ['employee']);
        $target = User::factory()->create();

        $this->actingAs($employee)->patch(route('admin.users.update', $target), [
            'name' => 'x', 'email' => 'x@example.com',
        ])->assertForbidden();

        $this->actingAs($employee)->patch(route('admin.users.password', $target), [
            'password' => 'whatever-123', 'password_confirmation' => 'whatever-123',
        ])->assertForbidden();

        $this->actingAs($employee)->patch(route('admin.users.toggle', $target))->assertForbidden();
    }
}
