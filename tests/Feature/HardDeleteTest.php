<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EmployeeTransfer;
use App\Models\Feedback;
use App\Models\Holiday;
use App\Models\HrDailyLog;
use App\Models\HrWeeklySummary;
use App\Models\KpiIndicator;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PlanAndReport;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HardDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_removes_a_department_and_the_rows_that_would_block_it(): void
    {
        $health = $this->department('Health');
        $education = $this->department('Education');
        $admin = $this->user('super_admin', null);
        $chief = $this->user('zone_chief', null);
        $employee = $this->user('employee', $health);
        $neighbor = $this->user('employee', $education);
        $health->update(['head_id' => $employee->id]);
        $kpi = $this->kpi($health);
        $otherKpi = $this->kpi($education);
        $plan = $this->plan($employee, $health, $kpi);
        $crossPlan = $this->plan($neighbor, $education, $kpi, ['eth_period' => 5, 'title' => 'Uses the health indicator']);
        $keptPlan = $this->plan($neighbor, $education, $otherKpi, ['eth_period' => 6, 'title' => 'Stays']);
        $feedback = Feedback::query()->create([
            'plan_and_report_id' => $plan->id,
            'department_id' => $health->id,
            'sender_id' => $employee->id,
            'comment' => 'Department note',
        ]);
        $leave = $this->leave($employee, $health);
        $log = $this->log($employee, $health);
        $summary = $this->summary($health);
        $payroll = $this->payroll($employee, $health);
        $inbound = EmployeeTransfer::query()->create([
            'user_id' => $neighbor->id,
            'from_department_id' => $education->id,
            'to_department_id' => $health->id,
            'effective_on' => '2024-09-01',
        ]);
        $outbound = EmployeeTransfer::query()->create([
            'user_id' => $employee->id,
            'from_department_id' => $health->id,
            'to_department_id' => $education->id,
            'effective_on' => '2024-09-02',
        ]);

        $this->actingAs($chief)->delete(route('departments.destroy', $health))->assertForbidden();
        $this->assertDatabaseHas('departments', ['id' => $health->id, 'is_active' => true]);

        $this->actingAs($admin)->delete(route('departments.destroy', $health))
            ->assertRedirect(route('departments.index'));

        $this->assertDatabaseMissing('departments', ['id' => $health->id]);
        $this->assertDatabaseMissing('kpi_indicators', ['id' => $kpi->id]);
        $this->assertDatabaseMissing('plans_and_reports', ['id' => $plan->id]);
        $this->assertDatabaseMissing('plans_and_reports', ['id' => $crossPlan->id]);
        $this->assertDatabaseMissing('feedbacks', ['id' => $feedback->id]);
        $this->assertDatabaseMissing('leave_requests', ['id' => $leave->id]);
        $this->assertDatabaseMissing('hr_daily_logs', ['id' => $log->id]);
        $this->assertDatabaseMissing('hr_weekly_summaries', ['id' => $summary->id]);
        $this->assertDatabaseMissing('payrolls', ['id' => $payroll->id]);
        $this->assertDatabaseMissing('employee_transfers', ['id' => $inbound->id]);
        $this->assertDatabaseHas('employee_transfers', ['id' => $outbound->id, 'from_department_id' => null]);
        $this->assertDatabaseHas('departments', ['id' => $education->id]);
        $this->assertDatabaseHas('plans_and_reports', ['id' => $keptPlan->id]);
        $this->assertDatabaseHas('users', ['id' => $employee->id]);
        $this->assertNull($employee->fresh()->department_id);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_super_admin_removes_a_user_and_a_kpi_without_opening_the_action_to_other_roles(): void
    {
        $health = $this->department('Health');
        $education = $this->department('Education');
        $admin = $this->user('super_admin', null);
        $employee = $this->user('employee', $health);
        $head = $this->user('dept_head', $health);
        $neighbor = $this->user('employee', $education);
        $health->update(['head_id' => $employee->id, 'acting_head_id' => $employee->id, 'acting_until' => '2024-12-31']);
        $kpi = $this->kpi($health);
        $owned = $this->plan($employee, $health, $kpi);
        $approved = $this->plan($neighbor, $education, $this->kpi($education), [
            'eth_period' => 8,
            'approved_by' => $employee->id,
            'status' => 'approved',
        ]);
        $onOwnedPlan = Feedback::query()->create([
            'plan_and_report_id' => $owned->id,
            'department_id' => $health->id,
            'sender_id' => $head->id,
            'receiver_id' => $employee->id,
            'comment' => 'On the employee plan',
        ]);
        $sent = Feedback::query()->create([
            'plan_and_report_id' => $approved->id,
            'department_id' => $education->id,
            'sender_id' => $employee->id,
            'receiver_id' => $neighbor->id,
            'comment' => 'Sent by the employee',
        ]);
        $received = Feedback::query()->create([
            'department_id' => $health->id,
            'sender_id' => $head->id,
            'receiver_id' => $employee->id,
            'comment' => 'Only received',
        ]);
        $leave = $this->leave($employee, $health);
        $ownLog = $this->log($employee, $health, '2024-09-16');
        $recorded = $this->log($neighbor, $education, '2024-09-17', $employee->id);
        $ownPay = $this->payroll($employee, $health, 2);
        $paid = $this->payroll($neighbor, $education, 3, $employee->id);
        $moved = EmployeeTransfer::query()->create([
            'user_id' => $employee->id,
            'from_department_id' => $health->id,
            'to_department_id' => $education->id,
            'transferred_by' => $admin->id,
            'effective_on' => '2024-09-01',
        ]);
        $summary = $this->summary($health, $employee->id);

        $this->actingAs($head)->delete(route('admin.users.destroy', $employee))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $employee))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $employee->id]);
        $this->assertDatabaseMissing('plans_and_reports', ['id' => $owned->id]);
        $this->assertDatabaseMissing('feedbacks', ['id' => $onOwnedPlan->id]);
        $this->assertDatabaseMissing('feedbacks', ['id' => $sent->id]);
        $this->assertDatabaseMissing('leave_requests', ['id' => $leave->id]);
        $this->assertDatabaseMissing('hr_daily_logs', ['id' => $ownLog->id]);
        $this->assertDatabaseMissing('payrolls', ['id' => $ownPay->id]);
        $this->assertDatabaseMissing('employee_transfers', ['id' => $moved->id]);
        $this->assertDatabaseHas('feedbacks', ['id' => $received->id, 'receiver_id' => null]);
        $this->assertDatabaseHas('plans_and_reports', ['id' => $approved->id, 'approved_by' => null]);
        $this->assertDatabaseHas('hr_daily_logs', ['id' => $recorded->id, 'recorded_by' => null]);
        $this->assertDatabaseHas('payrolls', ['id' => $paid->id, 'paid_by' => null]);
        $this->assertDatabaseHas('hr_weekly_summaries', ['id' => $summary->id, 'prepared_by' => null]);
        $this->assertDatabaseHas('departments', ['id' => $health->id, 'head_id' => null, 'acting_head_id' => null]);
        $this->assertNull($health->fresh()->acting_until);

        $this->actingAs($head)->delete(route('kpis.destroy', $kpi))->assertForbidden();
        $replacement = $this->plan($neighbor, $education, $kpi, ['eth_period' => 9, 'title' => 'Still tied to the indicator']);
        $note = Feedback::query()->create([
            'plan_and_report_id' => $replacement->id,
            'department_id' => $education->id,
            'sender_id' => $neighbor->id,
            'comment' => 'Tied to the indicator',
        ]);

        $this->actingAs($admin)->delete(route('kpis.destroy', $kpi))
            ->assertRedirect(route('kpis.index'));

        $this->assertDatabaseMissing('kpi_indicators', ['id' => $kpi->id]);
        $this->assertDatabaseMissing('plans_and_reports', ['id' => $replacement->id]);
        $this->assertDatabaseMissing('feedbacks', ['id' => $note->id]);
        $this->assertDatabaseHas('users', ['id' => $neighbor->id]);
    }

    public function test_allowed_roles_remove_the_other_record_rows(): void
    {
        $health = $this->department('Health');
        $employee = $this->user('employee', $health);
        $head = $this->user('dept_head', $health);
        $hr = $this->user('hr_specialist', $health);
        $finance = $this->user('finance_dept', null);
        $admin = $this->user('super_admin', null);
        $kpi = $this->kpi($health);
        $plan = $this->plan($employee, $health, $kpi);
        $feedback = Feedback::query()->create([
            'plan_and_report_id' => $plan->id,
            'department_id' => $health->id,
            'sender_id' => $head->id,
            'comment' => 'Draft comment',
        ]);
        $leave = $this->leave($employee, $health, 'draft');
        $holiday = Holiday::query()->create([
            'name' => 'Meskel',
            'name_am' => 'መስቀል',
            'eth_year' => 2017,
            'eth_month' => 1,
            'eth_day' => 17,
            'gregorian_date' => '2024-09-27',
            'is_recurring' => true,
        ]);
        $setting = Setting::query()->create(['key' => 'office_phone', 'value' => '011']);
        $log = $this->log($employee, $health);
        $summary = $this->summary($health, $hr->id);
        $payroll = $this->payroll($employee, $health);

        $this->actingAs($employee)->delete(route('plans.destroy', $plan))->assertRedirect(route('plans.index'));
        $this->assertDatabaseMissing('plans_and_reports', ['id' => $plan->id]);
        $this->assertDatabaseMissing('feedbacks', ['id' => $feedback->id]);

        $loose = Feedback::query()->create([
            'department_id' => $health->id,
            'sender_id' => $head->id,
            'comment' => 'Loose note',
        ]);
        $this->actingAs($head)->delete(route('feedbacks.destroy', $loose))->assertRedirect(route('feedbacks.index'));
        $this->assertDatabaseMissing('feedbacks', ['id' => $loose->id]);

        $this->actingAs($employee)->delete(route('leaves.destroy', $leave))->assertRedirect(route('leaves.index'));
        $this->assertDatabaseMissing('leave_requests', ['id' => $leave->id]);

        $this->actingAs($admin)->delete(route('holidays.destroy', $holiday))->assertRedirect();
        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);

        $this->actingAs($admin)->delete(route('admin.settings.records.destroy', $setting))->assertRedirect();
        $this->assertDatabaseMissing('settings', ['id' => $setting->id]);

        $this->actingAs($hr)->delete(route('hr.attendance.destroy', $log))->assertRedirect(route('hr.attendance.index'));
        $this->assertDatabaseMissing('hr_daily_logs', ['id' => $log->id]);

        $this->actingAs($hr)->delete(route('hr.weekly.destroy', $summary))->assertRedirect(route('hr.weekly.index'));
        $this->assertDatabaseMissing('hr_weekly_summaries', ['id' => $summary->id]);

        $this->actingAs($finance)->delete(route('payrolls.destroy', $payroll))->assertRedirect(route('payrolls.index'));
        $this->assertDatabaseMissing('payrolls', ['id' => $payroll->id]);
    }

    private function department(string $name): Department
    {
        return Department::query()->create([
            'name' => $name.' '.Str::upper(Str::random(3)),
            'code' => Str::upper(Str::random(5)),
            'is_active' => true,
        ]);
    }

    private function user(string $role, ?Department $department): User
    {
        return User::factory()->create([
            'role' => $role,
            'department_id' => $department?->id,
            'status' => 'active',
        ]);
    }

    private function kpi(Department $department): KpiIndicator
    {
        return KpiIndicator::query()->create([
            'department_id' => $department->id,
            'title' => 'Indicator '.Str::random(4),
            'unit' => 'number',
            'weight' => 1,
            'is_active' => true,
        ]);
    }

    private function plan(User $owner, Department $department, KpiIndicator $kpi, array $extra = []): PlanAndReport
    {
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
            'status' => 'draft',
            'is_rollup' => false,
            'period_start' => '2024-09-11',
            'period_end' => '2024-10-10',
        ], $extra));
    }

    private function leave(User $owner, Department $department, string $status = 'approved'): LeaveRequest
    {
        return LeaveRequest::query()->create([
            'user_id' => $owner->id,
            'department_id' => $department->id,
            'type' => 'annual',
            'start_date' => '2024-10-01',
            'end_date' => '2024-10-03',
            'days' => 3,
            'reason' => 'Family visit',
            'status' => $status,
        ]);
    }

    private function log(User $owner, Department $department, string $date = '2024-09-16', ?int $recordedBy = null): HrDailyLog
    {
        return HrDailyLog::query()->create([
            'user_id' => $owner->id,
            'department_id' => $department->id,
            'work_date' => $date,
            'eth_year' => 2017,
            'eth_month' => 1,
            'eth_day' => 6,
            'status' => 'present',
            'hours' => 8,
            'recorded_by' => $recordedBy,
        ]);
    }

    private function summary(Department $department, ?int $preparedBy = null): HrWeeklySummary
    {
        return HrWeeklySummary::query()->create([
            'department_id' => $department->id,
            'eth_year' => 2017,
            'eth_period' => 7,
            'period_start' => '2024-09-16',
            'period_end' => '2024-09-22',
            'status' => 'draft',
            'prepared_by' => $preparedBy,
        ]);
    }

    private function payroll(User $owner, Department $department, int $month = 2, ?int $paidBy = null): Payroll
    {
        return Payroll::query()->create([
            'user_id' => $owner->id,
            'department_id' => $department->id,
            'eth_year' => 2017,
            'eth_month' => $month,
            'base_salary' => 10000,
            'performance_score' => 80,
            'attendance_ratio' => 1,
            'bonus_or_adjustment' => 0,
            'net_pay' => 10000,
            'status' => 'calculated',
            'paid_by' => $paidBy,
        ]);
    }
}
