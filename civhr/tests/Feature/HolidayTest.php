<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create();
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user->fresh();
    }

    public function test_the_2026_proclamation_seeds_and_is_idempotent(): void
    {
        $this->seed(HolidaySeeder::class);
        $this->seed(HolidaySeeder::class);

        // 12 regular holidays (incl. both Eids) + 8 special non-working days.
        $this->assertSame(20, Holiday::count());
        $this->assertSame('Ninoy Aquino Day', Holiday::whereDate('date', '2026-08-21')->value('name'));
        // Feb 25 is a special WORKING day in 2026 — deliberately absent.
        $this->assertNull(Holiday::whereDate('date', '2026-02-25')->first());
    }

    public function test_an_admin_can_add_and_remove_a_holiday(): void
    {
        $admin = $this->userWithRoles(['admin']);

        $this->actingAs($admin)->get(route('admin.holidays.index'))->assertOk();

        $this->actingAs($admin)->post(route('admin.holidays.store'), [
            'date' => '2027-01-01', 'name' => "New Year's Day",
        ])->assertRedirect();
        $this->assertSame(1, Holiday::count());

        // The same date twice is a validation error, not a duplicate.
        $this->actingAs($admin)->post(route('admin.holidays.store'), [
            'date' => '2027-01-01', 'name' => 'Duplicate',
        ])->assertSessionHasErrors('date');

        $this->actingAs($admin)->delete(route('admin.holidays.destroy', Holiday::first()))
            ->assertRedirect();
        $this->assertSame(0, Holiday::count());
    }

    public function test_a_regular_employee_cannot_touch_holidays(): void
    {
        $employee = $this->userWithRoles(['employee']);

        $this->actingAs($employee)->get(route('admin.holidays.index'))->assertForbidden();
        $this->actingAs($employee)->post(route('admin.holidays.store'), [
            'date' => '2027-01-01', 'name' => 'Nope',
        ])->assertForbidden();
    }
}
