<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\KpiIndicator;
use App\Models\PlanAndReport;
use App\Models\Setting;
use App\Models\User;
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
