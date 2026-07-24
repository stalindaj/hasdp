<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Support\CreditLedger;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(LeaveTypeSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['employee_id' => Employee::create([
            'emp_no' => '5112', 'first_name' => 'Marie Cris', 'last_name' => 'Uri',
        ])->id]);
        $u->roles()->sync(Role::whereIn('name', ['admin', 'hr_officer'])->pluck('id'));

        return $u->fresh();
    }

    public function test_the_leave_card_prints_with_the_running_balance(): void
    {
        $admin = $this->admin();
        $employee = Employee::create([
            'emp_no' => '5807', 'first_name' => 'Justin', 'last_name' => 'Bercades',
            'office_department' => 'Directorate for Personnel',
            'date_orig_appt' => '2026-01-01',
            'credits_accrual_start' => '2026-01-01',
        ]);

        // Some accrual + an approved VL leave that draws 3 days.
        CreditLedger::balances($employee);   // posts the monthly accruals
        $applicant = User::factory()->create(['employee_id' => $employee->id]);
        $leave = LeaveApplication::create([
            'user_id' => $applicant->id,
            'employee_id' => $employee->id,
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'date_from' => '2026-03-09', 'date_to' => '2026-03-11',
            'working_days' => 3, 'status' => 'approved', 'decision' => 'approved',
            'days_with_pay' => 3, 'date_filing' => '2026-03-01', 'commutation' => 'not_requested',
        ]);
        CreditLedger::applyForApplication($leave->fresh(['leaveType', 'employee']));

        $this->actingAs($admin)->get(route('dashboard.ledger', $employee))
            ->assertOk()
            ->assertSee('LEAVE CARD')
            ->assertSee('Bercades, Justin')
            ->assertSee('Directorate for Personnel')
            // The absence row is on the card, on the VL side.
            ->assertSee('(VL)')
            // Prepared/Certified/Noted footer is present.
            ->assertSee('CERTIFIED CORRECT BY:');

        // The printed VL balance equals the live ledger balance.
        $this->assertStringContainsString(
            (string) rtrim(rtrim(number_format(CreditLedger::balances($employee)['vl'], 3, '.', ''), '0'), '.'),
            $this->actingAs($admin)->get(route('dashboard.ledger', $employee))->getContent()
        );
    }

    public function test_the_leave_card_is_admin_only(): void
    {
        $employee = Employee::create(['emp_no' => '5807', 'first_name' => 'Justin', 'last_name' => 'Bercades']);
        $emp = User::factory()->create(['employee_id' => $employee->id]);
        $emp->roles()->sync(Role::where('name', 'employee')->pluck('id'));

        $this->actingAs($emp)->get(route('dashboard.ledger', $employee))->assertForbidden();
    }
}
