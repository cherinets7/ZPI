<?php

namespace Tests\Feature;

use App\Exceptions\PayrollException;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\HrDailyLog;
use App\Models\HrWeeklySummary;
use App\Models\KpiIndicator;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PlanAndReport;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\PayrollService;
use App\Support\EthiopianDateHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_compute_weights_attendance_and_performance_and_does_not_deduct_a_flat_five_birr(): void
    {
        $result = app(PayrollService::class)->compute(10000, 80, 1, 0);

        $this->assertEqualsWithDelta(9400.0, $result['net_pay'], 0.02);
        $this->assertEqualsWithDelta(-600.0, $result['bonus_or_adjustment'], 0.02);
        $this->assertNotEqualsWithDelta(9995.0, $result['net_pay'], 0.001);

        $partial = app(PayrollService::class)->compute(5000, 100, 0.5, 0);
        $this->assertEqualsWithDelta(3250.0, $partial['net_pay'], 0.02);
    }

    public function test_department_payroll_excludes_holidays_and_leave_and_rolls_back_when_a_score_is_missing(): void
    {
        Setting::putValue('attendance_weight', '0.70');
        Setting::putValue('performance_weight', '0.30');
        Setting::putValue('hours_per_day', '8');

        $department = Department::query()->create(['name' => 'Health', 'code' => 'HLT1', 'is_active' => true]);
        $finance = User::factory()->create(['role' => 'finance_dept']);
        $ready = User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
            'base_salary' => 8000,
        ]);
        $missing = User::factory()->create([
            'role' => 'employee',
            'department_id' => $department->id,
            'base_salary' => 8000,
        ]);

        $calendarYear = 2016;
        $calendarMonth = 4;
        $this->seedMonth($department, $ready, $calendarYear, $calendarMonth, withScore: true);
        $this->approveWeeks($department, $calendarYear, $calendarMonth);

        $service = app(PayrollService::class);
        $attendance = app(AttendanceService::class)->summarize($ready, $calendarYear, $calendarMonth);

        try {
            $service->calculateDepartment($department, $calendarYear, $calendarMonth, $finance);
            $this->fail('Payroll should roll back when an employee has no approved score.');
        } catch (PayrollException $exception) {
            $this->assertNotEmpty($exception->errors);
        }

        $this->assertSame(0, Payroll::query()->count());

        $missing->update(['status' => 'inactive']);
        $created = $service->calculateDepartment($department, $calendarYear, $calendarMonth, $finance);
        $payroll = $created[0]->fresh();

        $expected = round((8000 * $attendance['ratio'] * 0.70) + (8000 * 1 * 0.30), 2);
        $this->assertEqualsWithDelta($expected, (float) $payroll->net_pay, 0.05);
        $this->assertNotEqualsWithDelta(7995.0, (float) $payroll->net_pay, 0.001);
        $this->assertLessThan(1, (float) $payroll->attendance_ratio);
        $this->assertSame('calculated', $payroll->status);

        $paid = $service->markPaid($payroll, $finance);
        $this->assertSame('paid', $paid->status);
        $this->assertNotEmpty($paid->verification_hash);

        $this->actingAs($ready)->get(route('payrolls.pdf', $paid))->assertOk();
        $this->get(route('documents.verify', $paid->verification_hash))->assertOk()->assertSee('authentic', false);
    }

    public function test_recurring_holiday_is_excluded_from_a_later_year(): void
    {
        $year = 2016;
        $month = 4;
        $bounds = EthiopianDateHelper::calendarMonthBounds($year, $month);
        $working = [];
        $cursor = $bounds['start']->copy();
        while ($cursor->lte($bounds['end'])) {
            if (EthiopianDateHelper::isWorkingDay($cursor)) {
                $working[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        $recurringDay = $working[0];
        $plainDay = $working[1];
        $recurringEth = EthiopianDateHelper::fromCarbon($recurringDay);
        $plainEth = EthiopianDateHelper::fromCarbon($plainDay);

        Holiday::query()->create([
            'name' => 'Recurring fixture',
            'gregorian_date' => EthiopianDateHelper::toCarbon($recurringEth['year'] - 1, $recurringEth['month'], $recurringEth['day'])->toDateString(),
            'eth_year' => $recurringEth['year'] - 1,
            'eth_month' => $recurringEth['month'],
            'eth_day' => $recurringEth['day'],
            'is_recurring' => true,
        ]);
        Holiday::query()->create([
            'name' => 'One-off fixture',
            'gregorian_date' => EthiopianDateHelper::toCarbon($plainEth['year'] - 1, $plainEth['month'], $plainEth['day'])->toDateString(),
            'eth_year' => $plainEth['year'] - 1,
            'eth_month' => $plainEth['month'],
            'eth_day' => $plainEth['day'],
            'is_recurring' => false,
        ]);

        $department = Department::query()->create(['name' => 'Health', 'code' => 'HLDY', 'is_active' => true]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $department->id]);
        foreach ($working as $day) {
            if (in_array($day->toDateString(), [$recurringDay->toDateString(), $plainDay->toDateString()], true)) {
                continue;
            }
            $eth = EthiopianDateHelper::fromCarbon($day);
            HrDailyLog::query()->create([
                'user_id' => $employee->id,
                'department_id' => $department->id,
                'work_date' => $day->toDateString(),
                'eth_year' => $eth['year'],
                'eth_month' => $eth['month'],
                'eth_day' => $eth['day'],
                'status' => 'present',
                'hours' => 8,
                'recorded_by' => $employee->id,
            ]);
        }

        $service = app(AttendanceService::class);
        $recurringClass = $service->classifyDay($employee, $recurringDay);
        $this->assertSame('holiday', $recurringClass['status']);
        $this->assertSame(0, $recurringClass['absent']);
        $this->assertEquals(0.0, $recurringClass['expected']);

        $plainClass = $service->classifyDay($employee, $plainDay);
        $this->assertSame('absent', $plainClass['status']);
        $this->assertSame(1, $plainClass['absent']);

        $classifiedExpected = 0.0;
        $classifiedAbsent = 0;
        foreach ($working as $day) {
            $classified = $service->classifyDay($employee, $day);
            $classifiedExpected += $classified['expected'];
            $classifiedAbsent += $classified['absent'];
        }

        $summary = $service->summarize($employee, $year, $month);
        $this->assertSame(1, $classifiedAbsent);
        $this->assertSame(1, $summary['holiday_days']);
        $this->assertEqualsWithDelta($classifiedExpected, $summary['expected'], 0.01);
        $this->assertGreaterThan(0, $summary['absent_days']);
    }

    public function test_payroll_is_refused_when_weekly_attendance_is_not_approved(): void
    {
        $department = Department::query()->create(['name' => 'Education', 'code' => 'EDU1', 'is_active' => true]);
        $finance = User::factory()->create(['role' => 'finance_dept']);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $department->id, 'base_salary' => 5000]);
        $this->seedMonth($department, $employee, 2016, 4, withScore: true);

        $this->expectException(PayrollException::class);
        app(PayrollService::class)->calculateDepartment($department, 2016, 4, $finance);
    }

    private function seedMonth(Department $department, User $employee, int $year, int $month, bool $withScore): void
    {
        $bounds = EthiopianDateHelper::calendarMonthBounds($year, $month);
        [$budgetYear, $index] = EthiopianDateHelper::budgetPosition($year, $month);
        $cursor = $bounds['start']->copy();
        $holiday = null;
        $leave = null;
        $absent = null;

        while ($cursor->lte($bounds['end'])) {
            if (EthiopianDateHelper::isWorkingDay($cursor)) {
                if (! $holiday) {
                    $holiday = $cursor->copy();
                } elseif (! $leave) {
                    $leave = $cursor->copy();
                } elseif (! $absent) {
                    $absent = $cursor->copy();
                } else {
                    $eth = EthiopianDateHelper::fromCarbon($cursor);
                    HrDailyLog::query()->create([
                        'user_id' => $employee->id,
                        'department_id' => $department->id,
                        'work_date' => $cursor->toDateString(),
                        'eth_year' => $eth['year'],
                        'eth_month' => $eth['month'],
                        'eth_day' => $eth['day'],
                        'status' => 'present',
                        'hours' => 8,
                        'recorded_by' => $employee->id,
                    ]);
                }
            }
            $cursor->addDay();
        }

        Holiday::query()->create([
            'name' => 'Fixture holiday '.$employee->id,
            'gregorian_date' => $holiday->toDateString(),
            'eth_year' => $year,
            'eth_month' => $month,
            'eth_day' => 1,
            'is_recurring' => false,
        ]);

        LeaveRequest::query()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'type' => 'annual',
            'start_date' => $leave->toDateString(),
            'end_date' => $leave->toDateString(),
            'days' => 1,
            'reason' => 'Approved annual leave',
            'status' => 'approved',
        ]);

        if ($withScore) {
            $kpi = KpiIndicator::query()->create([
                'department_id' => $department->id,
                'title' => 'Coverage '.$employee->id,
                'unit' => 'percent',
                'weight' => 1,
                'is_active' => true,
            ]);
            PlanAndReport::query()->create([
                'user_id' => $employee->id,
                'department_id' => $department->id,
                'kpi_indicator_id' => $kpi->id,
                'type' => 'report',
                'period_type' => 'monthly',
                'eth_year' => $budgetYear,
                'eth_period' => $index,
                'title' => 'Monthly coverage',
                'target_value' => 100,
                'achieved_value' => 100,
                'performance_score' => 100,
                'status' => 'approved',
                'is_rollup' => false,
                'period_start' => $bounds['start']->toDateString(),
                'period_end' => $bounds['end']->toDateString(),
            ]);
        }
    }

    private function approveWeeks(Department $department, int $year, int $month): void
    {
        foreach (app(AttendanceService::class)->weeksOverlapping($year, $month) as $week) {
            HrWeeklySummary::query()->create([
                'department_id' => $department->id,
                'eth_year' => $week['eth_year'],
                'eth_period' => $week['eth_period'],
                'period_start' => $week['start']->toDateString(),
                'period_end' => $week['end']->toDateString(),
                'expected_hours' => 40,
                'worked_hours' => 32,
                'status' => 'approved',
            ]);
        }
    }
}
