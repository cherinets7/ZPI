<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\HrWeeklySummary;
use App\Models\KpiIndicator;
use App\Models\PlanAndReport;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\RollupService;
use App\Support\EthiopianDateHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReportingPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_periods_lock_and_approved_weeklies_roll_up(): void
    {
        Setting::putValue('lock_grace_days', '0');
        $department = Department::query()->create(['name' => 'Health', 'code' => 'ROLL', 'is_active' => true]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $department->id]);
        $kpi = KpiIndicator::query()->create([
            'department_id' => $department->id,
            'title' => 'Visits',
            'unit' => 'number',
            'weight' => 2,
            'is_active' => true,
        ]);

        $old = $this->weekly($employee, $department, $kpi, 1, '2020-01-06', '2020-01-12', 10, 8);
        $current = $this->weekly($employee, $department, $kpi, 2, now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString(), 10, 9);
        $current->forceFill(['eth_year' => EthiopianDateHelper::budgetYearOf(now()), 'period_end' => now()->toDateString()])->save();

        Artisan::call('reporting:lock-expired');

        $this->assertNotNull($old->fresh()->locked_at);
        $this->assertNull($current->fresh()->locked_at);

        $first = $this->weekly($employee, $department, $kpi, 10, '2024-09-16', '2024-09-22', 40, 20, 2017);
        $second = $this->weekly($employee, $department, $kpi, 11, '2024-09-23', '2024-09-29', 40, 30, 2017);
        $first->forceFill(['status' => 'approved'])->save();
        $second->forceFill(['status' => 'approved'])->save();

        app(RollupService::class)->rollupFromWeekly($second->fresh());

        $monthly = PlanAndReport::query()
            ->where('is_rollup', true)
            ->where('period_type', 'monthly')
            ->where('user_id', $employee->id)
            ->first();

        $this->assertNotNull($monthly);
        $this->assertEquals(50.0, (float) $monthly->achieved_value);
        $this->assertEquals(80.0, (float) $monthly->target_value);
        $this->assertEquals(62.5, (float) $monthly->performance_score);
        $this->assertSame('approved', $monthly->status);

        foreach (['quarterly', 'semi_annual', 'annual'] as $period) {
            $this->assertTrue(
                PlanAndReport::query()->where('is_rollup', true)->where('period_type', $period)->where('user_id', $employee->id)->exists(),
                $period.' rollup was not created.'
            );
        }
    }

    public function test_hamle_week_uses_one_key_for_summaries_and_payroll(): void
    {
        $budgetYear = $this->budgetYearWithMidweekHamle();
        $hamle = EthiopianDateHelper::budgetYearStart($budgetYear);
        $monday = EthiopianDateHelper::mondayOf($hamle);
        $sunday = $monday->copy()->addDays(6);

        $this->assertNotSame(1, (int) $hamle->isoWeekday());
        $this->assertTrue($hamle->gt($monday) && $hamle->lte($sunday));

        $fromMonday = EthiopianDateHelper::weekIdentity($monday);
        $fromHamle = EthiopianDateHelper::weekIdentity($hamle);
        $fromSunday = EthiopianDateHelper::weekIdentity($sunday);

        $this->assertSame($budgetYear, $fromHamle['budget_year']);
        $this->assertSame(1, $fromHamle['week']);
        $this->assertSame(
            [$fromMonday['budget_year'], $fromMonday['week'], $fromMonday['start']->toDateString(), $fromMonday['end']->toDateString()],
            [$fromHamle['budget_year'], $fromHamle['week'], $fromHamle['start']->toDateString(), $fromHamle['end']->toDateString()]
        );
        $this->assertSame(
            [$fromSunday['budget_year'], $fromSunday['week'], $fromSunday['start']->toDateString(), $fromSunday['end']->toDateString()],
            [$fromHamle['budget_year'], $fromHamle['week'], $fromHamle['start']->toDateString(), $fromHamle['end']->toDateString()]
        );

        $previous = EthiopianDateHelper::weekIdentity($monday->copy()->subDay());
        $this->assertFalse($previous['budget_year'] === $fromHamle['budget_year'] && $previous['week'] === $fromHamle['week']);

        $bounds = EthiopianDateHelper::periodBounds('weekly', $budgetYear, 1);
        $this->assertTrue($bounds['start']->equalTo($monday));
        $this->assertTrue($bounds['end']->equalTo($sunday));

        $department = Department::query()->create(['name' => 'Health', 'code' => 'HAMLE', 'is_active' => true]);
        $hr = User::factory()->create(['role' => 'hr_specialist', 'department_id' => $department->id]);

        $this->actingAs($hr)->post(route('hr.weekly.generate'), ['date' => $sunday->toDateString()])->assertRedirect();
        $this->actingAs($hr)->post(route('hr.weekly.generate'), ['date' => $monday->toDateString()])->assertRedirect();

        $this->assertSame(1, HrWeeklySummary::query()->count());
        $summary = HrWeeklySummary::query()->first();
        $this->assertSame($fromHamle['budget_year'], (int) $summary->eth_year);
        $this->assertSame($fromHamle['week'], (int) $summary->eth_period);
        $summary->update(['status' => 'approved']);

        $calendar = EthiopianDateHelper::fromCarbon($hamle);
        $attendance = app(AttendanceService::class);
        $matched = false;
        foreach ($attendance->weeksOverlapping($calendar['year'], $calendar['month']) as $week) {
            if ((int) $week['eth_year'] === (int) $summary->eth_year && (int) $week['eth_period'] === (int) $summary->eth_period) {
                $matched = true;

                continue;
            }

            HrWeeklySummary::query()->create([
                'department_id' => $department->id,
                'eth_year' => $week['eth_year'],
                'eth_period' => $week['eth_period'],
                'period_start' => $week['start']->toDateString(),
                'period_end' => $week['end']->toDateString(),
                'expected_hours' => 0,
                'worked_hours' => 0,
                'status' => 'approved',
            ]);
        }

        $this->assertTrue($matched);
        $attendance->assertMonthVerified($department, $calendar['year'], $calendar['month']);
    }

    public function test_boundary_week_stays_in_the_annual_rollup(): void
    {
        $budgetYear = null;
        $monday = null;
        $sunday = null;
        for ($year = 2018; $year < 2030; $year++) {
            $start = EthiopianDateHelper::budgetYearStart($year);
            $weekMonday = EthiopianDateHelper::mondayOf($start);
            if ($weekMonday->copy()->addDays(3)->gte($start)) {
                $budgetYear = $year;
                $monday = $weekMonday;
                $sunday = $weekMonday->copy()->addDays(6);
                break;
            }
        }
        $this->assertNotNull($budgetYear);

        $department = Department::query()->create(['name' => 'Health', 'code' => 'BNDRY', 'is_active' => true]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $department->id]);
        $kpi = KpiIndicator::query()->create([
            'department_id' => $department->id,
            'title' => 'Visits',
            'unit' => 'number',
            'weight' => 1,
            'is_active' => true,
        ]);

        $boundary = $this->weekly($employee, $department, $kpi, 1, $monday->toDateString(), $sunday->toDateString(), 40, 20, $budgetYear - 1);
        $boundary->forceFill(['status' => 'approved'])->save();
        app(RollupService::class)->rollupFromWeekly($boundary->fresh());

        $annual = PlanAndReport::query()
            ->where('is_rollup', true)
            ->where('period_type', 'annual')
            ->where('eth_year', $budgetYear)
            ->where('user_id', $employee->id)
            ->first();
        $this->assertNotNull($annual);
        $this->assertEquals(20.0, (float) $annual->achieved_value);
        $this->assertEquals(40.0, (float) $annual->target_value);

        $monthly = PlanAndReport::query()
            ->where('is_rollup', true)
            ->where('period_type', 'monthly')
            ->where('eth_year', $budgetYear)
            ->where('eth_period', 1)
            ->where('user_id', $employee->id)
            ->first();
        $quarter = PlanAndReport::query()
            ->where('is_rollup', true)
            ->where('period_type', 'quarterly')
            ->where('eth_year', $budgetYear)
            ->where('eth_period', 1)
            ->where('user_id', $employee->id)
            ->first();
        $this->assertEquals(20.0, (float) $monthly?->achieved_value);
        $this->assertEquals(20.0, (float) $quarter?->achieved_value);

        $meskeremMonday = EthiopianDateHelper::mondayOf(EthiopianDateHelper::toCarbon($budgetYear, 1, 10));
        $second = $this->weekly(
            $employee,
            $department,
            $kpi,
            20,
            $meskeremMonday->toDateString(),
            $meskeremMonday->copy()->addDays(6)->toDateString(),
            40,
            30,
            $budgetYear
        );
        $second->forceFill(['status' => 'approved'])->save();
        app(RollupService::class)->rollupFromWeekly($second->fresh());

        $this->assertEquals(50.0, (float) $annual->fresh()->achieved_value);
        $this->assertEquals(80.0, (float) $annual->fresh()->target_value);
        $this->assertEquals(20.0, (float) $monthly->fresh()->achieved_value);
        $this->assertEquals(20.0, (float) $quarter->fresh()->achieved_value);

        $semi = PlanAndReport::query()
            ->where('is_rollup', true)
            ->where('period_type', 'semi_annual')
            ->where('eth_year', $budgetYear)
            ->where('eth_period', 1)
            ->where('user_id', $employee->id)
            ->first();
        $this->assertEquals(50.0, (float) $semi?->achieved_value);
    }

    private function budgetYearWithMidweekHamle(): int
    {
        for ($year = 2017; $year < 2030; $year++) {
            if ((int) EthiopianDateHelper::budgetYearStart($year)->isoWeekday() !== 1) {
                return $year;
            }
        }

        $this->fail('No mid-week Hamle 1 found.');
    }

    private function weekly(User $user, Department $department, KpiIndicator $kpi, int $period, string $start, string $end, float $target, float $achieved, int $year = 2012): PlanAndReport
    {
        return PlanAndReport::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'kpi_indicator_id' => $kpi->id,
            'type' => 'report',
            'period_type' => 'weekly',
            'eth_year' => $year,
            'eth_period' => $period,
            'title' => 'Weekly visits',
            'target_value' => $target,
            'achieved_value' => $achieved,
            'performance_score' => round(($achieved / $target) * 100, 2),
            'status' => 'submitted',
            'is_rollup' => false,
            'period_start' => $start,
            'period_end' => $end,
        ]);
    }
}
