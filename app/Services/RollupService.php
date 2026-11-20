<?php

namespace App\Services;

use App\Models\PlanAndReport;
use App\Support\EthiopianDateHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RollupService
{
    public function rollupFromWeekly(PlanAndReport $weekly): void
    {
        if ($weekly->period_type !== 'weekly' || $weekly->type !== 'report' || $weekly->status !== 'approved' || $weekly->is_rollup) {
            return;
        }

        $budgetYear = $this->thursdayBudgetYear($weekly);
        $weeklies = $this->weekliesForBudgetYear($weekly, $budgetYear);
        $eth = EthiopianDateHelper::fromCarbon($this->thursdayOf($weekly));
        [, $monthIndex] = EthiopianDateHelper::budgetPosition($eth['year'], $eth['month']);

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
            return $this->thursdayBudgetYear($row) === $budgetYear;
        });
    }

    /**
     * Approved weeklies whose Thursday falls in the budget year, including a
     * boundary week stored under the neighbouring eth_year.
     *
     * @return Collection<int, PlanAndReport>
     */
    private function weekliesForBudgetYear(PlanAndReport $source, int $budgetYear): Collection
    {
        $start = EthiopianDateHelper::budgetYearStart($budgetYear)->toDateString();
        $end = EthiopianDateHelper::budgetYearEnd($budgetYear)->toDateString();

        return PlanAndReport::query()
            ->where('user_id', $source->user_id)
            ->where('department_id', $source->department_id)
            ->where('kpi_indicator_id', $source->kpi_indicator_id)
            ->where('type', 'report')
            ->where('period_type', 'weekly')
            ->where('is_rollup', false)
            ->where('status', 'approved')
            ->where(function ($query) use ($source, $budgetYear, $start, $end) {
                $query->where('id', $source->id)
                    ->orWhere(function ($query) use ($budgetYear, $start, $end) {
                        $query->whereBetween('eth_year', [$budgetYear - 1, $budgetYear + 1])
                            ->where(function ($query) use ($budgetYear, $start, $end) {
                                $query->where('eth_year', $budgetYear)
                                    ->orWhereBetween('period_start', [$start, $end])
                                    ->orWhereBetween('period_end', [$start, $end]);
                            });
                    });
            })
            ->get()
            ->filter(fn (PlanAndReport $row) => $this->thursdayBudgetYear($row) === $budgetYear)
            ->values();
    }

    private function thursdayBudgetYear(PlanAndReport $weekly): int
    {
        $eth = EthiopianDateHelper::fromCarbon($this->thursdayOf($weekly));
        [$year] = EthiopianDateHelper::budgetPosition($eth['year'], $eth['month']);

        return $year;
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

    private function thursdayOf(PlanAndReport $weekly): Carbon
    {
        if ($weekly->period_start) {
            return EthiopianDateHelper::mondayOf($weekly->period_start)->addDays(3);
        }

        $bounds = EthiopianDateHelper::periodBounds('weekly', (int) $weekly->eth_year, (int) $weekly->eth_period);

        return $bounds['start']->copy()->addDays(3);
    }
}
