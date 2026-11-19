<?php

namespace App\Services;

use App\Models\PlanAndReport;
use App\Support\EthiopianDateHelper;
use Illuminate\Support\Collection;

class RollupService
{
    public function rollupFromWeekly(PlanAndReport $weekly): void
    {
        if ($weekly->period_type !== 'weekly' || $weekly->type !== 'report' || $weekly->status !== 'approved' || $weekly->is_rollup) {
            return;
        }

        $weeklies = PlanAndReport::query()
            ->where('user_id', $weekly->user_id)
            ->where('department_id', $weekly->department_id)
            ->where('kpi_indicator_id', $weekly->kpi_indicator_id)
            ->where('type', 'report')
            ->where('period_type', 'weekly')
            ->where('is_rollup', false)
            ->where('status', 'approved')
            ->where('eth_year', $weekly->eth_year)
            ->get();

        $budgetYear = (int) $weekly->eth_year;
        $anchor = $this->thursdayOf($weekly);
        $eth = EthiopianDateHelper::fromCarbon($anchor);
        [$anchorYear, $monthIndex] = EthiopianDateHelper::budgetPosition($eth['year'], $eth['month']);

        if ($anchorYear !== $budgetYear) {
            $budgetYear = $anchorYear;
        }

        $quarter = match (true) {
            $monthIndex <= 3 => 1,
            $monthIndex <= 6 => 2,
            $monthIndex <= 9 => 3,
            default => 4,
        };
        $half = $monthIndex <= 6 ? 1 : 2;

        $this->upsert($weekly, $weeklies, 'monthly', $budgetYear, $monthIndex, function (PlanAndReport $row) use ($budgetYear, $monthIndex) {
            $eth = EthiopianDateHelper::fromCarbon($this->thursdayOf($row));
            [$year, $index] = EthiopianDateHelper::budgetPosition($eth['year'], $eth['month']);

            return $year === $budgetYear && $index === $monthIndex;
        });

        $this->upsert($weekly, $weeklies, 'quarterly', $budgetYear, $quarter, function (PlanAndReport $row) use ($budgetYear, $quarter) {
            $eth = EthiopianDateHelper::fromCarbon($this->thursdayOf($row));
            [$year, $index] = EthiopianDateHelper::budgetPosition($eth['year'], $eth['month']);
            $rowQuarter = match (true) {
                $index <= 3 => 1,
                $index <= 6 => 2,
                $index <= 9 => 3,
                default => 4,
            };

            return $year === $budgetYear && $rowQuarter === $quarter;
        });

        $this->upsert($weekly, $weeklies, 'semi_annual', $budgetYear, $half, function (PlanAndReport $row) use ($budgetYear, $half) {
            $eth = EthiopianDateHelper::fromCarbon($this->thursdayOf($row));
            [$year, $index] = EthiopianDateHelper::budgetPosition($eth['year'], $eth['month']);
            $rowHalf = $index <= 6 ? 1 : 2;

            return $year === $budgetYear && $rowHalf === $half;
        });

        $this->upsert($weekly, $weeklies, 'annual', $budgetYear, 1, function (PlanAndReport $row) use ($budgetYear) {
            return (int) $row->eth_year === $budgetYear;
        });
    }

    /**
     * @param  Collection<int, PlanAndReport>  $weeklies
     * @param  callable(PlanAndReport): bool  $filter
     */
    private function upsert(PlanAndReport $source, Collection $weeklies, string $periodType, int $budgetYear, int $period, callable $filter): void
    {
        $rows = $weeklies->filter($filter)->values();
        if ($rows->isEmpty()) {
            return;
        }

        $target = round((float) $rows->sum('target_value'), 2);
        $achieved = round((float) $rows->sum('achieved_value'), 2);
        $score = $target > 0 ? round(($achieved / $target) * 100, 2) : 0.0;
        $bounds = EthiopianDateHelper::periodBounds($periodType, $budgetYear, $period);

        PlanAndReport::query()->updateOrCreate(
            [
                'user_id' => $source->user_id,
                'kpi_indicator_id' => $source->kpi_indicator_id,
                'type' => 'report',
                'period_type' => $periodType,
                'eth_year' => $budgetYear,
                'eth_period' => $period,
                'is_rollup' => true,
            ],
            [
                'department_id' => $source->department_id,
                'title' => $source->title,
                'target_value' => $target,
                'achieved_value' => $achieved,
                'performance_score' => $score,
                'status' => 'approved',
                'period_start' => $bounds['start']->toDateString(),
                'period_end' => $bounds['end']->toDateString(),
                'notes' => 'Automatic rollup of approved weekly reports.',
                'approved_at' => now(),
            ]
        );
    }

    private function thursdayOf(PlanAndReport $weekly): \Illuminate\Support\Carbon
    {
        if ($weekly->period_start) {
            return EthiopianDateHelper::mondayOf($weekly->period_start)->addDays(3);
        }

        $bounds = EthiopianDateHelper::periodBounds('weekly', (int) $weekly->eth_year, (int) $weekly->eth_period);

        return $bounds['start']->copy()->addDays(3);
    }
}
