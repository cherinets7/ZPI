<?php

namespace App\Services;

use App\Exceptions\PayrollException;
use App\Models\Department;
use App\Models\Payroll;
use App\Models\PlanAndReport;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Support\EthiopianDateHelper;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(
        private AttendanceService $attendance,
        private AuditService $audit,
    ) {}

    /**
     * Pay is base salary weighted by verified attendance and the approved
     * performance score, plus an optional explicit bonus. There is no flat
     * cash deduction.
     *
     * @return array{base_salary: float, performance_score: float, attendance_ratio: float, bonus_or_adjustment: float, net_pay: float}
     */
    public function compute(float $baseSalary, float $performanceScore, float $attendanceRatio, float $manualBonus = 0): array
    {
        $attendanceWeight = (float) Setting::getValue('attendance_weight', config('workflow.default_attendance_weight'));
        $performanceWeight = (float) Setting::getValue('performance_weight', config('workflow.default_performance_weight'));
        $attendanceRatio = max(0, min(1, $attendanceRatio));
        $performanceRatio = max(0, $performanceScore / 100);
        $base = round($baseSalary, 2);

        $net = round(
            ($base * $attendanceRatio * $attendanceWeight)
            + ($base * $performanceRatio * $performanceWeight)
            + $manualBonus,
            2
        );

        return [
            'base_salary' => $base,
            'performance_score' => round($performanceScore, 2),
            'attendance_ratio' => round($attendanceRatio, 4),
            'bonus_or_adjustment' => round($net - $base, 2),
            'net_pay' => $net,
        ];
    }

    /**
     * @param  array<int, float|int|string>  $bonuses
     * @return list<Payroll>
     */
    public function calculateDepartment(Department $department, int $calendarYear, int $calendarMonth, User $actor, array $bonuses = []): array
    {
        if ($actor->role !== 'finance_dept') {
            throw new PayrollException(['Only the finance department can calculate payroll.']);
        }

        if ($calendarMonth < 1 || $calendarMonth > 13) {
            throw new PayrollException(['Choose an Ethiopian month from Meskerem to Pagume.']);
        }

        $employees = User::query()
            ->where('department_id', $department->id)
            ->where('role', 'employee')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        if ($employees->isEmpty()) {
            throw new PayrollException(['This department has no active employees.']);
        }

        $this->attendance->assertMonthVerified($department, $calendarYear, $calendarMonth);

        return DB::transaction(function () use ($employees, $department, $calendarYear, $calendarMonth, $actor, $bonuses) {
            $created = [];
            foreach ($employees as $employee) {
                $created[] = $this->calculateEmployee(
                    $employee,
                    $department,
                    $calendarYear,
                    $calendarMonth,
                    $actor,
                    (float) ($bonuses[$employee->id] ?? 0)
                );
            }

            return $created;
        });
    }

    public function calculateEmployee(
        User $employee,
        Department $department,
        int $calendarYear,
        int $calendarMonth,
        User $actor,
        float $manualBonus = 0,
    ): Payroll {
        $existing = Payroll::query()
            ->where('user_id', $employee->id)
            ->where('eth_year', $calendarYear)
            ->where('eth_month', $calendarMonth)
            ->first();

        if ($existing && $existing->status === 'paid') {
            throw new PayrollException(["Payroll for {$employee->name} is already paid and cannot be recalculated."]);
        }

        $attendance = $this->attendance->summarize($employee, $calendarYear, $calendarMonth);
        $score = $this->performanceScore($employee, $department, $calendarYear, $calendarMonth);
        if ($score === null) {
            throw new PayrollException(["No approved monthly performance score for {$employee->name}."]);
        }

        $money = $this->compute((float) $employee->base_salary, $score, $attendance['ratio'], $manualBonus);

        $payroll = Payroll::query()->updateOrCreate(
            [
                'user_id' => $employee->id,
                'eth_year' => $calendarYear,
                'eth_month' => $calendarMonth,
            ],
            [
                'department_id' => $department->id,
                'base_salary' => $money['base_salary'],
                'performance_score' => $money['performance_score'],
                'attendance_ratio' => $money['attendance_ratio'],
                'bonus_or_adjustment' => $money['bonus_or_adjustment'],
                'net_pay' => $money['net_pay'],
                'status' => 'calculated',
                'paid_by' => null,
                'paid_at' => null,
                'verification_hash' => null,
            ]
        );

        $this->audit->log('payroll.calculated', $payroll, [], $payroll->only([
            'base_salary', 'performance_score', 'attendance_ratio', 'bonus_or_adjustment', 'net_pay',
        ]), $department->id, $actor);

        return $payroll;
    }

    public function markPaid(Payroll $payroll, User $actor): Payroll
    {
        if ($actor->role !== 'finance_dept') {
            throw new PayrollException(['Only the finance department can issue a payslip.']);
        }

        if ($payroll->status === 'paid') {
            return $payroll;
        }

        return DB::transaction(function () use ($payroll, $actor) {
            $payroll->forceFill([
                'status' => 'paid',
                'paid_by' => $actor->id,
                'paid_at' => now(),
            ])->save();

            $payroll->forceFill([
                'verification_hash' => hash_hmac(
                    'sha256',
                    'payroll|'.$payroll->id.'|'.$payroll->net_pay.'|'.$payroll->paid_at?->timestamp,
                    (string) config('app.key')
                ),
            ])->save();

            $this->audit->log('payroll.paid', $payroll, [], [
                'net_pay' => $payroll->net_pay,
                'verification_hash' => $payroll->verification_hash,
            ], $payroll->department_id, $actor);

            $payroll->user?->notify(new WorkflowNotification(
                __('ui.payslip_ready'),
                __('ui.payslip_ready_body', ['period' => EthiopianDateHelper::monthName((int) $payroll->eth_month).' '.$payroll->eth_year]),
                route('payrolls.show', $payroll)
            ));

            return $payroll->refresh();
        });
    }

    public function performanceScore(User $employee, Department $department, int $calendarYear, int $calendarMonth): ?float
    {
        [$budgetYear, $index] = EthiopianDateHelper::budgetPosition($calendarYear, $calendarMonth);

        $reports = PlanAndReport::query()
            ->where('user_id', $employee->id)
            ->where('department_id', $department->id)
            ->where('type', 'report')
            ->where('period_type', 'monthly')
            ->where('eth_year', $budgetYear)
            ->where('eth_period', $index)
            ->where('status', 'approved')
            ->with('kpiIndicator')
            ->get();

        if ($reports->isEmpty()) {
            return null;
        }

        $chosen = $reports->groupBy('kpi_indicator_id')->map(function ($group) {
            return $group->firstWhere('is_rollup', false) ?? $group->first();
        });

        $weightSum = 0.0;
        $scoreSum = 0.0;
        foreach ($chosen as $report) {
            $weight = (float) ($report->kpiIndicator->weight ?? 1);
            if ($weight <= 0) {
                $weight = 1;
            }
            $weightSum += $weight;
            $scoreSum += ((float) $report->performance_score) * $weight;
        }

        if ($weightSum <= 0) {
            return null;
        }

        return round($scoreSum / $weightSum, 2);
    }
}
