<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\KpiIndicator;
use App\Models\PlanAndReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_roles_are_isolated_and_central_roles_cannot_manage_accounts(): void
    {
        $health = $this->department('Health');
        $education = $this->department('Education');
        $employee = $this->user('employee', $health);
        $other = $this->user('employee', $education);
        $planner = $this->user('dev_plan_specialist', $health);
        $planDept = $this->user('plan_dept', null);
        $chief = $this->user('zone_chief', null);
        $deputy = $this->user('zone_deputy', null);
        $admin = $this->user('super_admin', null);
        $own = $this->plan($employee, $health);
        $foreign = $this->plan($other, $education);

        $this->actingAs($employee)->get(route('plans.show', $foreign))->assertForbidden();
        $this->actingAs($employee)->get(route('plans.show', $own))->assertOk();
        $this->actingAs($planner)->get(route('plans.show', $foreign))->assertForbidden();
        $this->actingAs($planner)->get(route('plans.show', $own))->assertOk();
        $this->actingAs($planDept)->get(route('plans.show', $foreign))->assertOk();

        $this->actingAs($employee)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($chief)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($chief)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($deputy)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk();

        $this->actingAs($employee)->get(route('payrolls.create'))->assertForbidden();
    }

    public function test_inactive_users_and_department_roles_without_an_active_department_are_blocked(): void
    {
        $inactive = $this->user('super_admin', null, ['status' => 'inactive']);
        $this->actingAs($inactive)->get(route('dashboard'))->assertForbidden();

        $floating = $this->user('employee', null);
        $this->actingAs($floating)->get(route('dashboard'))->assertForbidden();

        $closed = $this->department('Closed');
        $closed->update(['is_active' => false]);
        $member = $this->user('hr_specialist', $closed);
        $this->actingAs($member)->get(route('dashboard'))->assertForbidden();
    }

    public function test_login_rejects_inactive_accounts_and_password_reset_is_throttled(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'password' => 'Secret@123',
            'status' => 'inactive',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret@123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $active = User::factory()->create([
            'role' => 'super_admin',
            'password' => 'Secret@123',
            'status' => 'active',
        ]);
        $this->post('/login', ['email' => $active->email, 'password' => 'Secret@123'])
            ->assertRedirect(route('dashboard'));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/forgot-password', ['email' => 'nobody-'.$attempt.'@example.com'])->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'nobody-final@example.com'])->assertStatus(429);
    }

    public function test_acting_head_can_approve_before_expiry_and_only_dept_head_or_admin_can_unlock(): void
    {
        $department = $this->department('Health');
        $head = $this->user('dept_head', $department);
        $department->update(['head_id' => $head->id]);
        $employee = $this->user('employee', $department);
        $acting = $this->user('employee', $department);
        $department->update([
            'acting_head_id' => $acting->id,
            'acting_until' => now()->toDateString(),
        ]);

        $plan = $this->plan($employee, $department, ['status' => 'verified', 'type' => 'report', 'achieved_value' => 80, 'target_value' => 100]);

        $this->actingAs($acting)->post(route('plans.approve', $plan), ['comment' => 'Accepted by the acting head.'])
            ->assertRedirect();
        $this->assertSame('approved', $plan->fresh()->status);
        $this->assertEquals(80.0, (float) $plan->fresh()->performance_score);

        $locked = $this->plan($employee, $department, [
            'status' => 'approved',
            'locked_at' => now(),
            'eth_period' => 2,
        ]);

        $this->actingAs($employee)->post(route('plans.unlock', $locked))->assertForbidden();
        $this->actingAs($acting)->post(route('plans.unlock', $locked))->assertForbidden();
        $this->assertNotNull($locked->fresh()->locked_at);

        $this->actingAs($head)->post(route('plans.unlock', $locked))->assertRedirect();
        $this->assertNull($locked->fresh()->locked_at);

        $lockedAgain = $this->plan($employee, $department, [
            'status' => 'approved',
            'locked_at' => now(),
            'eth_period' => 3,
        ]);
        $admin = $this->user('super_admin', null);
        $this->actingAs($admin)->post(route('plans.unlock', $lockedAgain))->assertRedirect();
        $this->assertNull($lockedAgain->fresh()->locked_at);

        $department->update(['acting_until' => now()->subDay()->toDateString()]);
        $next = $this->plan($employee, $department, ['status' => 'verified', 'type' => 'report', 'achieved_value' => 10, 'target_value' => 10, 'eth_period' => 4]);
        $this->actingAs($acting->fresh())->post(route('plans.approve', $next), ['comment' => 'Too late'])->assertForbidden();
    }

    public function test_employee_transfer_keeps_historical_department_on_existing_records(): void
    {
        $from = $this->department('Health');
        $to = $this->department('Education');
        $hr = $this->user('hr_specialist', $from);
        $employee = $this->user('employee', $from);
        $plan = $this->plan($employee, $from);

        $this->actingAs($hr)->post(route('hr.employees.transfer.store', $employee), [
            'to_department_id' => $to->id,
            'effective_on' => now()->toDateString(),
            'reason' => 'Reassigned after the reporting period.',
        ])->assertRedirect();

        $this->assertSame($to->id, $employee->fresh()->department_id);
        $this->assertSame($from->id, $plan->fresh()->department_id);
        $this->assertDatabaseHas('employee_transfers', [
            'user_id' => $employee->id,
            'from_department_id' => $from->id,
            'to_department_id' => $to->id,
        ]);
    }

    private function department(string $name): Department
    {
        return Department::query()->create([
            'name' => $name.' '.Str::upper(Str::random(3)),
            'code' => Str::upper(Str::random(5)),
            'is_active' => true,
        ]);
    }

    private function user(string $role, ?Department $department, array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'department_id' => $department?->id,
            'status' => 'active',
        ], $extra));
    }

    private function plan(User $owner, Department $department, array $extra = []): PlanAndReport
    {
        $kpi = KpiIndicator::query()->create([
            'department_id' => $department->id,
            'title' => 'Indicator '.Str::random(4),
            'unit' => 'number',
            'weight' => 1,
            'is_active' => true,
        ]);

        return PlanAndReport::query()->create(array_merge([
            'user_id' => $owner->id,
            'department_id' => $department->id,
            'kpi_indicator_id' => $kpi->id,
            'type' => 'plan',
            'period_type' => 'monthly',
            'eth_year' => 2017,
            'eth_period' => 4,
            'title' => 'Coverage plan',
            'target_value' => 100,
            'achieved_value' => null,
            'status' => 'draft',
            'is_rollup' => false,
            'period_start' => '2024-09-11',
            'period_end' => '2024-10-10',
        ], $extra));
    }
}
