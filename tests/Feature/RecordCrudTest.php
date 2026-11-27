<?php

namespace Tests\Feature;

use App\Models\Department;
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
use App\Services\PayrollService;
use App\Support\EthiopianDateHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecordCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_screens_follow_department_and_role_boundaries(): void
    {
        $health = $this->department('Health');
        $education = $this->department('Education');
        $employee = $this->user('employee', $health);
        $other = $this->user('employee', $education);
        $admin = $this->user('super_admin', null);
        $chief = $this->user('zone_chief', null);
        $kpi = $this->kpi($health);
        $foreignKpi = $this->kpi($education);
        $holiday = Holiday::query()->create([
            'name' => 'Meskel',
            'name_am' => 'መስቀል',
            'eth_year' => 2017,
            'eth_month' => 1,
            'eth_day' => 17,
            'gregorian_date' => '2024-09-27',
            'is_recurring' => true,
        ]);

        $this->actingAs($employee)->get(route('kpis.show', $kpi))->assertOk()->assertSee($kpi->title);
        $this->actingAs($employee)->get(route('kpis.show', $foreignKpi))->assertForbidden();
        $this->actingAs($other)->get(route('holidays.show', $holiday))->assertOk()->assertSee('Meskel');
        $this->actingAs($admin)->get(route('admin.users.show', $employee))->assertOk()->assertSee($employee->email);
        $this->actingAs($chief)->get(route('admin.users.show', $employee))->assertForbidden();
        $this->actingAs($chief)->get(route('admin.settings.records.create'))->assertForbidden();
        $this->actingAs($chief)->get(route('departments.show', $health))->assertOk();
        $this->actingAs($chief)->delete(route('departments.destroy', $health))->assertForbidden();
    }

    public function test_employee_cannot_delete_an_approved_or_locked_record(): void
    {
        $health = $this->department('Health');
        $employee = $this->user('employee', $health);
        $approved = $this->plan($employee, $health, ['status' => 'approved', 'type' => 'report', 'achieved_value' => 80]);
        $locked = $this->plan($employee, $health, [
            'status' => 'approved',
            'locked_at' => now(),
            'eth_period' => 5,
        ]);
        $leave = LeaveRequest::query()->create([
            'user_id' => $employee->id,
            'department_id' => $health->id,
            'type' => 'annual',
            'start_date' => '2024-10-01',
            'end_date' => '2024-10-03',
            'days' => 3,
            'reason' => 'Family visit',
            'status' => 'approved',
        ]);

        $this->actingAs($employee)->delete(route('plans.destroy', $approved))->assertForbidden();
        $this->actingAs($employee)->delete(route('plans.destroy', $locked))->assertForbidden();
        $this->actingAs($employee)->delete(route('leaves.destroy', $leave))->assertForbidden();
        $this->assertDatabaseHas('plans_and_reports', ['id' => $approved->id]);
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id]);
    }

    public function test_feedback_stays_inside_the_department_and_cannot_be_deleted_once_approved(): void
    {
        $health = $this->department('Health');
        $education = $this->department('Education');
        $employee = $this->user('employee', $health);
        $outsider = $this->user('employee', $education);
        $head = $this->user('dept_head', $health);
        $health->update(['head_id' => $head->id]);
        $plan = $this->plan($employee, $health);

        $this->actingAs($employee)->get(route('feedbacks.create'))->assertForbidden();
        $this->actingAs($head)->post(route('feedbacks.store'), [
            'plan_and_report_id' => $plan->id,
            'comment' => 'Widen the clinic coverage.',
        ])->assertRedirect();

        $feedback = Feedback::query()->firstOrFail();
        $this->actingAs($employee)->get(route('feedbacks.show', $feedback))->assertOk()->assertSee('Widen the clinic coverage.');
        $this->actingAs($outsider)->get(route('feedbacks.show', $feedback))->assertForbidden();
        $this->actingAs($employee)->delete(route('feedbacks.destroy', $feedback))->assertForbidden();

        $this->actingAs($head)->put(route('feedbacks.update', $feedback), [
            'comment' => 'Widen the clinic coverage this month.',
        ])->assertRedirect(route('feedbacks.show', $feedback));
        $this->assertSame('Widen the clinic coverage this month.', $feedback->fresh()->comment);

        $plan->update(['status' => 'approved']);
        $this->actingAs($head)->delete(route('feedbacks.destroy', $feedback))->assertForbidden();
        $this->assertDatabaseHas('feedbacks', ['id' => $feedback->id]);

        $open = $this->plan($employee, $health, ['eth_period' => 6, 'title' => 'Still a draft']);
        $this->actingAs($head)->post(route('feedbacks.store'), [
            'plan_and_report_id' => $open->id,
            'comment' => 'A draft note.',
        ])->assertRedirect();
        $draftNote = Feedback::query()->where('plan_and_report_id', $open->id)->firstOrFail();
        $this->actingAs($head)->delete(route('feedbacks.destroy', $draftNote))->assertRedirect(route('feedbacks.index'));
        $this->assertDatabaseMissing('feedbacks', ['id' => $draftNote->id]);
    }

    public function test_attendance_and_weekly_summaries_are_edited_only_by_the_department_hr(): void
    {
        $health = $this->department('Health');
        $education = $this->department('Education');
        $hr = $this->user('hr_specialist', $health);
        $otherHr = $this->user('hr_specialist', $education);
        $employee = $this->user('employee', $health);
        $chief = $this->user('zone_chief', null);
        $date = Carbon::parse('2024-09-16');

        $this->actingAs($hr)->post(route('hr.attendance.entries.store'), [
            'user_id' => $employee->id,
            'work_date' => $date->toDateString(),
            'status' => 'present',
            'hours' => 6,
            'note' => 'Morning clinic',
        ])->assertRedirect();

        $log = HrDailyLog::query()->firstOrFail();
        $this->assertSame('present', $log->status);
        $this->assertEquals(6.0, (float) $log->hours);

        $this->actingAs($otherHr)->get(route('hr.attendance.show', $log))->assertForbidden();
        $this->actingAs($employee)->put(route('hr.attendance.update', $log), [
            'work_date' => $date->toDateString(),
            'status' => 'absent',
            'hours' => 0,
        ])->assertForbidden();
        $this->actingAs($chief)->get(route('hr.attendance.show', $log))->assertOk();
        $this->actingAs($chief)->put(route('hr.attendance.update', $log), [
            'work_date' => $date->toDateString(),
            'status' => 'absent',
            'hours' => 0,
        ])->assertForbidden();

        $this->actingAs($hr)->put(route('hr.attendance.update', $log), [
            'work_date' => $date->toDateString(),
            'status' => 'absent',
            'hours' => 8,
        ])->assertRedirect();
        $this->assertSame('absent', $log->fresh()->status);
        $this->assertEquals(0.0, (float) $log->fresh()->hours);

        $identity = EthiopianDateHelper::weekIdentity($date);
        $summary = HrWeeklySummary::query()->create([
            'department_id' => $health->id,
            'eth_year' => $identity['budget_year'],
            'eth_period' => $identity['week'],
            'period_start' => $identity['start']->toDateString(),
            'period_end' => $identity['end']->toDateString(),
            'expected_hours' => 40,
            'worked_hours' => 30,
            'absent_days' => 1,
            'leave_days' => 0,
            'holiday_days' => 0,
            'status' => 'draft',
            'prepared_by' => $hr->id,
        ]);

        $this->actingAs($hr)->get(route('hr.weekly.create'))->assertOk();
        $this->actingAs($hr)->put(route('hr.weekly.update', $summary), [
            'review_note' => 'Checked the clinic log.',
            'worked_hours' => 1,
        ])->assertRedirect();
        $summary->refresh();
        $this->assertSame('Checked the clinic log.', $summary->review_note);
        $this->assertEquals(30.0, (float) $summary->worked_hours);

        $this->actingAs($otherHr)->delete(route('hr.weekly.destroy', $summary))->assertForbidden();
        $this->actingAs($employee)->delete(route('hr.weekly.destroy', $summary))->assertForbidden();

        $summary->update(['status' => 'approved']);
        $this->actingAs($hr)->delete(route('hr.weekly.destroy', $summary))->assertForbidden();
        $this->actingAs($hr)->put(route('hr.attendance.update', $log), [
            'work_date' => $date->toDateString(),
            'status' => 'present',
            'hours' => 8,
        ])->assertForbidden();
        $this->assertSame('absent', $log->fresh()->status);
    }

    public function test_finance_can_edit_an_unpaid_payroll_and_nobody_can_change_it_after_payment(): void
    {
        $health = $this->department('Health');
        $employee = $this->user('employee', $health);
        $finance = $this->user('finance_dept', null);
        $chief = $this->user('zone_chief', null);
        $payrolls = app(PayrollService::class);
        $initial = $payrolls->compute(10000, 80, 1, 0);
        $payroll = Payroll::query()->create([
            'user_id' => $employee->id,
            'department_id' => $health->id,
            'eth_year' => 2017,
            'eth_month' => 2,
            'base_salary' => $initial['base_salary'],
            'performance_score' => $initial['performance_score'],
            'attendance_ratio' => $initial['attendance_ratio'],
            'bonus_or_adjustment' => $initial['bonus_or_adjustment'],
            'net_pay' => $initial['net_pay'],
            'status' => 'calculated',
        ]);

        $this->actingAs($finance)->get(route('payrolls.edit', $payroll))->assertOk();
        $this->actingAs($finance)->put(route('payrolls.update', $payroll), ['bonus' => 150])->assertRedirect();
        $expected = $payrolls->compute(10000, 80, 1, 150);
        $payroll->refresh();
        $this->assertEquals($expected['net_pay'], (float) $payroll->net_pay);
        $this->assertEquals($expected['bonus_or_adjustment'], (float) $payroll->bonus_or_adjustment);
        $this->assertSame('calculated', $payroll->status);
        $this->assertNull($payroll->paid_at);

        $this->actingAs($employee)->delete(route('payrolls.destroy', $payroll))->assertForbidden();
        $this->actingAs($chief)->put(route('payrolls.update', $payroll), ['bonus' => 1])->assertForbidden();

        $payroll->forceFill(['status' => 'paid', 'paid_at' => now(), 'paid_by' => $finance->id])->save();
        $this->actingAs($finance)->put(route('payrolls.update', $payroll), ['bonus' => 999])->assertForbidden();
        $this->actingAs($finance)->delete(route('payrolls.destroy', $payroll))->assertForbidden();
        $this->assertEquals($expected['net_pay'], (float) $payroll->fresh()->net_pay);
    }

    public function test_super_admin_manages_custom_settings_but_not_payroll_keys(): void
    {
        $admin = $this->user('super_admin', null);
        Setting::putValue('attendance_weight', '0.30');
        Setting::putValue('performance_weight', '0.70');
        $weight = Setting::query()->where('key', 'attendance_weight')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.settings.records.store'), [
            'key' => 'attendance_weight',
            'value' => '0.10',
        ])->assertSessionHasErrors('key');

        $this->actingAs($admin)->post(route('admin.settings.records.store'), [
            'key' => 'office_phone',
            'value' => '011-000-0000',
        ])->assertRedirect();
        $phone = Setting::query()->where('key', 'office_phone')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.settings.records.show', $phone))->assertOk()->assertSee('011-000-0000');
        $this->actingAs($admin)->put(route('admin.settings.records.update', $phone), [
            'value' => '011-111-1111',
        ])->assertRedirect();
        $this->assertSame('011-111-1111', $phone->fresh()->value);

        $this->actingAs($admin)->delete(route('admin.settings.records.destroy', $weight))->assertForbidden();
        $this->assertDatabaseHas('settings', ['key' => 'attendance_weight']);

        $this->actingAs($admin)->put(route('admin.settings.records.update', $weight), [
            'value' => '0.90',
        ])->assertSessionHasErrors('value');

        $this->actingAs($admin)->delete(route('admin.settings.records.destroy', $phone))->assertRedirect();
        $this->assertDatabaseMissing('settings', ['key' => 'office_phone']);
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

    private function plan(User $owner, Department $department, array $extra = []): PlanAndReport
    {
        return PlanAndReport::query()->create(array_merge([
            'user_id' => $owner->id,
            'department_id' => $department->id,
            'kpi_indicator_id' => $this->kpi($department)->id,
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
