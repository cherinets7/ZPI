<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\HrDailyLog;
use App\Models\HrWeeklySummary;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\EthiopianDateHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeaveAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reproduce_leave_and_field_work_against_weekday_attendance(): void
    {
        $department = Department::query()->create(['name' => 'Health', 'code' => 'HLTX', 'is_active' => true]);
        $head = User::factory()->create(['role' => 'dept_head', 'department_id' => $department->id]);
        $department->update(['head_id' => $head->id]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $department->id, 'base_salary' => 8000]);
        $hr = User::factory()->create(['role' => 'hr_specialist', 'department_id' => $department->id]);

        $monday = Carbon::parse('2026-10-05')->startOfDay();
        $friday = Carbon::parse('2026-10-09')->startOfDay();
        $this->assertTrue(EthiopianDateHelper::isWorkingDay($monday));
        $this->assertSame(5, $friday->dayOfWeekIso);

        $this->actingAs($employee)->post(route('leaves.store'), [
            'type' => 'annual',
            'start_date' => $monday->toDateString(),
            'end_date' => $friday->toDateString(),
            'reason' => 'Family visit',
        ])->assertRedirect();

        $leave = LeaveRequest::query()->firstOrFail();
        $this->assertEquals(5, (float) $leave->days);

        $this->actingAs($employee)->post(route('leaves.submit', $leave))->assertRedirect();
        $this->actingAs($head)->post(route('leaves.reject', $leave), [
            'review_note' => 'Dates overlap a clinic.',
        ])->assertRedirect();
        $this->assertSame('rejected', $leave->fresh()->status);

        $this->actingAs($employee)->put(route('leaves.update', $leave), [
            'type' => 'annual',
            'start_date' => $monday->toDateString(),
            'end_date' => $friday->toDateString(),
            'reason' => 'Family visit, revised',
        ])->assertRedirect();
        $this->actingAs($employee)->post(route('leaves.submit', $leave))->assertRedirect();
        $this->assertSame('submitted', $leave->fresh()->status);

        $this->actingAs($head)->post(route('leaves.approve', $leave), [
            'review_note' => 'Approved.',
        ])->assertRedirect();
        $this->assertSame('approved', $leave->fresh()->status);

        $service = app(AttendanceService::class);
        $cursor = $monday->copy();
        while ($cursor->lte($friday)) {
            $day = $service->classifyDay($employee, $cursor);
            $this->assertSame('leave', $day['status'], $cursor->toDateString());
            $this->assertSame(0, $day['absent'], $cursor->toDateString());
            $this->assertEquals(0.0, $day['expected'], $cursor->toDateString());
            $cursor->addDay();
        }

        $eth = EthiopianDateHelper::fromCarbon($monday);
        $bounds = EthiopianDateHelper::calendarMonthBounds($eth['year'], $eth['month']);
        $other = $bounds['start']->copy();
        while ($other->lte($bounds['end'])) {
            if (EthiopianDateHelper::isWorkingDay($other) && ($other->lt($monday) || $other->gt($friday))) {
                HrDailyLog::query()->create([
                    'user_id' => $employee->id,
                    'department_id' => $department->id,
                    'work_date' => $other->toDateString(),
                    'eth_year' => $eth['year'],
                    'eth_month' => $eth['month'],
                    'eth_day' => $eth['day'],
                    'status' => 'absent',
                    'hours' => 0,
                    'recorded_by' => $hr->id,
                ]);
                break;
            }
            $other->addDay();
        }

        // An absent log on a covered weekday must not add a penalty.
        HrDailyLog::query()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'work_date' => $monday->toDateString(),
            'eth_year' => $eth['year'],
            'eth_month' => $eth['month'],
            'eth_day' => $eth['day'],
            'status' => 'absent',
            'hours' => 0,
            'recorded_by' => $hr->id,
        ]);

        $summary = $service->summarize($employee, $eth['year'], $eth['month']);
        $week = $service->summarizeDepartmentWeek($department, $monday);
        $this->assertSame(5, $week['leave_days']);
        $this->assertSame(0, $week['absent_days']);
        $this->assertGreaterThan(0, $summary['leave_days']);

        $page = $this->actingAs($hr)->get(route('hr.attendance.index', ['date' => $monday->toDateString()]));
        $page->assertOk();
        $page->assertDontSee('name="rows[0][work_date]" value="'.$monday->toDateString().'"', false);
        $this->assertSame(5, substr_count($page->getContent(), 'badge-status-leave'), $page->getContent());

        $fieldMonday = Carbon::parse('2026-10-12')->startOfDay();
        $fieldFriday = Carbon::parse('2026-10-16')->startOfDay();
        $this->actingAs($employee)->post(route('leaves.store'), [
            'type' => 'field_work',
            'start_date' => $fieldMonday->toDateString(),
            'end_date' => $fieldFriday->toDateString(),
            'reason' => 'Outreach',
        ])->assertRedirect();
        $field = LeaveRequest::query()->where('type', 'field_work')->firstOrFail();
        $this->actingAs($employee)->post(route('leaves.submit', $field))->assertRedirect();
        $this->actingAs($head)->post(route('leaves.approve', $field))->assertRedirect();

        $fieldDay = $service->classifyDay($employee, $fieldMonday);
        $this->assertSame('field_work', $fieldDay['status']);
        $this->assertSame(0, $fieldDay['absent']);
        $this->assertEquals(8.0, $fieldDay['expected']);
        $this->assertEquals(8.0, $fieldDay['worked']);

        $fieldWeek = $service->summarizeDepartmentWeek($department, $fieldMonday);
        $this->assertSame(0, $fieldWeek['absent_days']);
        $this->assertEquals(40.0, $fieldWeek['expected']);
        $this->assertEquals(40.0, $fieldWeek['worked']);

        $fieldPage = $this->actingAs($hr)->get(route('hr.attendance.index', ['date' => $fieldMonday->toDateString()]));
        $fieldPage->assertOk();
        $this->assertSame(5, substr_count($fieldPage->getContent(), 'badge-status-field_work'), $fieldPage->getContent());

        $month = EthiopianDateHelper::fromCarbon($monday);
        $bounds = EthiopianDateHelper::calendarMonthBounds($month['year'], $month['month']);
        $classifiedAbsent = 0;
        $classifiedLeave = 0;
        $day = $bounds['start']->copy();
        while ($day->lte($bounds['end'])) {
            $classified = $service->classifyDay($employee, $day);
            $classifiedAbsent += $classified['absent'];
            $classifiedLeave += $classified['leave'];
            $day->addDay();
        }
        $monthSummary = $service->summarize($employee, $month['year'], $month['month']);
        $this->assertSame($classifiedAbsent, $monthSummary['absent_days'], 'month summary penalised a covered weekday');
        $this->assertSame($classifiedLeave, $monthSummary['leave_days']);
    }

    public function test_a_weekly_summary_generated_before_approval_drops_the_absence_penalty(): void
    {
        $department = Department::query()->create(['name' => 'Health', 'code' => 'HLTW', 'is_active' => true]);
        $head = User::factory()->create(['role' => 'dept_head', 'department_id' => $department->id]);
        $department->update(['head_id' => $head->id]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $department->id, 'base_salary' => 8000]);
        $hr = User::factory()->create(['role' => 'hr_specialist', 'department_id' => $department->id]);

        $monday = Carbon::parse('2026-10-05')->startOfDay();
        $friday = Carbon::parse('2026-10-09')->startOfDay();
        $cursor = $monday->copy();
        while ($cursor->lte($friday)) {
            $eth = EthiopianDateHelper::fromCarbon($cursor);
            HrDailyLog::query()->create([
                'user_id' => $employee->id,
                'department_id' => $department->id,
                'work_date' => $cursor->toDateString(),
                'eth_year' => $eth['year'],
                'eth_month' => $eth['month'],
                'eth_day' => $eth['day'],
                'status' => 'absent',
                'hours' => 0,
                'recorded_by' => $hr->id,
            ]);
            $cursor->addDay();
        }

        $this->actingAs($hr)->post(route('hr.weekly.generate'), [
            'date' => $monday->toDateString(),
        ])->assertRedirect();
        $summary = HrWeeklySummary::query()->firstOrFail();
        $this->assertSame(5, $summary->absent_days);
        $summary->update(['status' => 'forwarded']);

        $this->actingAs($employee)->post(route('leaves.store'), [
            'type' => 'annual',
            'start_date' => $monday->toDateString(),
            'end_date' => $friday->toDateString(),
            'reason' => 'Clinic coverage',
        ])->assertRedirect();
        $leave = LeaveRequest::query()->firstOrFail();
        $this->actingAs($employee)->post(route('leaves.submit', $leave))->assertRedirect();
        $this->actingAs($head)->post(route('leaves.reject', $leave), [
            'review_note' => 'Use field work.',
        ])->assertRedirect();
        $this->actingAs($employee)->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertSee(__('ui.resubmit'), false)
            ->assertSee('name="type"', false)
            ->assertSee('value="field_work"', false);
        $this->actingAs($employee)->put(route('leaves.update', $leave), [
            'type' => 'field_work',
            'start_date' => $monday->toDateString(),
            'end_date' => $friday->toDateString(),
            'reason' => 'Outreach clinics',
        ])->assertRedirect();
        $this->actingAs($employee)->post(route('leaves.submit', $leave))->assertRedirect();
        $this->actingAs($head)->post(route('leaves.approve', $leave))->assertRedirect();

        $summary->refresh();
        $this->assertSame('forwarded', $summary->status);
        $this->assertSame(0, $summary->absent_days);
        $this->assertSame(0, $summary->leave_days);
        $this->assertEquals((float) $summary->expected_hours, (float) $summary->worked_hours);
        $this->assertGreaterThan(0, (float) $summary->worked_hours);
    }
}
