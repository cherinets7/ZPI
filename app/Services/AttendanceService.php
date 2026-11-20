<?php

namespace App\Services;

use App\Exceptions\PayrollException;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\HrDailyLog;
use App\Models\HrWeeklySummary;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Models\User;
use App\Support\EthiopianDateHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * @return list<array{start: Carbon, end: Carbon, eth_year: int, eth_period: int}>
     */
    public function weeksOverlapping(int $calendarYear, int $calendarMonth): array
    {
        $bounds = EthiopianDateHelper::calendarMonthBounds($calendarYear, $calendarMonth);
        $cursor = EthiopianDateHelper::mondayOf($bounds['start']);
        $weeks = [];

        while ($cursor->lte($bounds['end'])) {
            $identity = EthiopianDateHelper::weekIdentity($cursor);
            $weeks[] = [
                'start' => $identity['start']->copy(),
                'end' => $identity['end']->copy(),
                'eth_year' => $identity['budget_year'],
                'eth_period' => $identity['week'],
            ];
            $cursor->addDays(7);
        }

        return $weeks;
    }

    public function assertMonthVerified(Department $department, int $calendarYear, int $calendarMonth): void
    {
        $errors = [];
        foreach ($this->weeksOverlapping($calendarYear, $calendarMonth) as $week) {
            if ($week['end']->copy()->startOfDay()->gt(now()->startOfDay())) {
                $errors[] = 'The Ethiopian month still has an open week, so payroll cannot be calculated yet.';
                break;
            }

            $summary = HrWeeklySummary::query()
                ->where('department_id', $department->id)
                ->where('eth_year', $week['eth_year'])
                ->where('eth_period', $week['eth_period'])
                ->first();

            if (! $summary || $summary->status !== 'approved') {
                $errors[] = "Weekly attendance for budget year {$week['eth_year']}, week {$week['eth_period']} is not approved.";
            }
        }

        if ($errors !== []) {
            throw new PayrollException($errors);
        }
    }

    /**
     * @return array{expected: float, worked: float, ratio: float, absent_days: int, leave_days: int, holiday_days: int}
     */
    public function summarize(User $employee, int $calendarYear, int $calendarMonth): array
    {
        $bounds = EthiopianDateHelper::calendarMonthBounds($calendarYear, $calendarMonth);
        $hoursPerDay = (float) Setting::getValue('hours_per_day', config('workflow.default_hours_per_day'));
        $expected = 0.0;
        $worked = 0.0;
        $absentDays = 0;
        $leaveDays = 0;
        $holidayDays = 0;

        $holidaySet = $this->holidayDates($bounds['start'], $bounds['end']);

        $leaves = LeaveRequest::query()
            ->where('user_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $bounds['end']->toDateString())
            ->whereDate('end_date', '>=', $bounds['start']->toDateString())
            ->get();

        $logs = HrDailyLog::query()
            ->where('user_id', $employee->id)
            ->whereBetween('work_date', [$bounds['start']->toDateString(), $bounds['end']->toDateString()])
            ->get()
            ->keyBy(fn (HrDailyLog $log) => $log->work_date->toDateString());

        $cursor = $bounds['start']->copy();
        while ($cursor->lte($bounds['end'])) {
            if (EthiopianDateHelper::isWorkingDay($cursor)) {
                $key = $cursor->toDateString();
                $leave = $this->coveringLeave($leaves, $cursor);

                if (isset($holidaySet[$key])) {
                    $holidayDays++;
                } elseif ($leave && $leave->type !== 'field_work') {
                    $leaveDays++;
                } elseif ($leave && $leave->type === 'field_work') {
                    $expected += $hoursPerDay;
                    $worked += $hoursPerDay;
                } else {
                    $expected += $hoursPerDay;
                    $log = $logs->get($key);
                    if ($log && in_array($log->status, ['present', 'field_work'], true)) {
                        $worked += (float) $log->hours;
                    } else {
                        $absentDays++;
                    }
                }
            }
            $cursor->addDay();
        }

        $ratio = $expected <= 0 ? 1.0 : min(1, $worked / $expected);

        return [
            'expected' => round($expected, 2),
            'worked' => round($worked, 2),
            'ratio' => round($ratio, 4),
            'absent_days' => $absentDays,
            'leave_days' => $leaveDays,
            'holiday_days' => $holidayDays,
        ];
    }

    /**
     * @param  Collection<int, LeaveRequest>  $leaves
     */
    public function coveringLeave(Collection $leaves, Carbon $date): ?LeaveRequest
    {
        return $leaves->first(function (LeaveRequest $leave) use ($date) {
            return $date->toDateString() >= $leave->start_date->toDateString()
                && $date->toDateString() <= $leave->end_date->toDateString();
        });
    }

    /**
     * @return array{expected: float, worked: float, absent_days: int, leave_days: int, holiday_days: int}
     */
    public function summarizeDepartmentWeek(Department $department, Carbon $monday): array
    {
        $identity = EthiopianDateHelper::weekIdentity($monday);
        $totals = [
            'expected' => 0.0,
            'worked' => 0.0,
            'absent_days' => 0,
            'leave_days' => 0,
            'holiday_days' => 0,
        ];

        $employees = User::query()
            ->where('department_id', $department->id)
            ->where('role', 'employee')
            ->where('status', 'active')
            ->get();

        $cursor = $identity['start']->copy();
        while ($cursor->lte($identity['end'])) {
            if (EthiopianDateHelper::isWorkingDay($cursor)) {
                $eth = EthiopianDateHelper::fromCarbon($cursor);
                foreach ($employees as $employee) {
                    $day = $this->classifyDay($employee, $cursor);
                    $totals['expected'] += $day['expected'];
                    $totals['worked'] += $day['worked'];
                    $totals['absent_days'] += $day['absent'];
                    $totals['leave_days'] += $day['leave'];
                    $totals['holiday_days'] += $day['holiday'];
                    unset($eth);
                }
            }
            $cursor->addDay();
        }

        return [
            'expected' => round($totals['expected'], 2),
            'worked' => round($totals['worked'], 2),
            'absent_days' => $totals['absent_days'],
            'leave_days' => $totals['leave_days'],
            'holiday_days' => $totals['holiday_days'],
            'eth_year' => $identity['budget_year'],
            'eth_period' => $identity['week'],
            'start' => $identity['start'],
            'end' => $identity['end'],
        ];
    }

    /**
     * @return array{expected: float, worked: float, absent: int, leave: int, holiday: int, status: string}
     */
    public function classifyDay(User $employee, Carbon $date): array
    {
        $hoursPerDay = (float) Setting::getValue('hours_per_day', config('workflow.default_hours_per_day'));
        $empty = ['expected' => 0.0, 'worked' => 0.0, 'absent' => 0, 'leave' => 0, 'holiday' => 0, 'status' => 'weekend'];

        if (! EthiopianDateHelper::isWorkingDay($date)) {
            return $empty;
        }

        if ($this->isHoliday($date)) {
            return ['expected' => 0.0, 'worked' => 0.0, 'absent' => 0, 'leave' => 0, 'holiday' => 1, 'status' => 'holiday'];
        }

        $leave = LeaveRequest::query()
            ->where('user_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();

        if ($leave && $leave->type !== 'field_work') {
            return ['expected' => 0.0, 'worked' => 0.0, 'absent' => 0, 'leave' => 1, 'holiday' => 0, 'status' => 'leave'];
        }

        if ($leave && $leave->type === 'field_work') {
            return ['expected' => $hoursPerDay, 'worked' => $hoursPerDay, 'absent' => 0, 'leave' => 0, 'holiday' => 0, 'status' => 'field_work'];
        }

        $log = HrDailyLog::query()
            ->where('user_id', $employee->id)
            ->whereDate('work_date', $date->toDateString())
            ->first();

        if ($log && in_array($log->status, ['present', 'field_work'], true)) {
            return [
                'expected' => $hoursPerDay,
                'worked' => (float) $log->hours,
                'absent' => 0,
                'leave' => 0,
                'holiday' => 0,
                'status' => $log->status,
            ];
        }

        return ['expected' => $hoursPerDay, 'worked' => 0.0, 'absent' => 1, 'leave' => 0, 'holiday' => 0, 'status' => 'absent'];
    }

    /**
     * @return array<string, true>
     */
    private function holidayDates(Carbon $start, Carbon $end): array
    {
        $dates = Holiday::query()
            ->whereBetween('gregorian_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('gregorian_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $set = [];
        foreach ($dates as $date) {
            $set[$date] = true;
        }

        $recurring = Holiday::query()->where('is_recurring', true)->get(['eth_month', 'eth_day']);
        if ($recurring->isEmpty()) {
            return $set;
        }

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $eth = EthiopianDateHelper::fromCarbon($cursor);
            foreach ($recurring as $holiday) {
                if ((int) $holiday->eth_month === $eth['month'] && (int) $holiday->eth_day === $eth['day']) {
                    $set[$cursor->toDateString()] = true;
                    break;
                }
            }
            $cursor->addDay();
        }

        return $set;
    }

    private function isHoliday(Carbon $date): bool
    {
        if (Holiday::query()->whereDate('gregorian_date', $date->toDateString())->exists()) {
            return true;
        }

        $eth = EthiopianDateHelper::fromCarbon($date);

        return Holiday::query()
            ->where('is_recurring', true)
            ->where('eth_month', $eth['month'])
            ->where('eth_day', $eth['day'])
            ->exists();
    }
}
