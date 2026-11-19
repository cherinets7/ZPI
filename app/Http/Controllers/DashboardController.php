<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\HrDailyLog;
use App\Models\Payroll;
use App\Models\PlanAndReport;
use App\Models\Setting;
use App\Support\EthiopianDateHelper;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $budgetYear = Setting::budgetYear();
        $counts = [
            'draft' => 0,
            'submitted' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        $planQuery = PlanAndReport::query()->visibleTo($user)->where('is_rollup', false);
        foreach (array_keys($counts) as $status) {
            $counts[$status] = (clone $planQuery)->where('status', $status)->count();
        }

        $charts = [];
        $leaderboard = collect();
        $payrollTotal = null;
        $attendance = null;

        if ($user->hasRole('employee')) {
            $rows = PlanAndReport::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->where('period_type', 'monthly')
                ->where('eth_year', $budgetYear)
                ->with('kpiIndicator')
                ->get();
            $charts[] = $this->bar(__('ui.performance'), $rows->map(fn ($row) => $row->kpiIndicator?->title ?? $row->title)->all(), $rows->map(fn ($row) => (float) $row->performance_score)->all());
        }

        if ($user->hasRole('plan_dept', 'zone_chief', 'zone_deputy', 'super_admin', 'dept_head', 'dev_plan_specialist') || $user->isActingHead()) {
            $scores = PlanAndReport::query()
                ->where('status', 'approved')
                ->where('period_type', 'monthly')
                ->where('eth_year', $budgetYear)
                ->whereNotNull('performance_score')
                ->selectRaw('department_id, avg(performance_score) as score')
                ->groupBy('department_id')
                ->pluck('score', 'department_id');

            if ($user->isDepartmentRole() && ! $user->seesAllDepartments()) {
                $scores = $scores->only([(int) $user->department_id]);
            }

            $departments = Department::query()->whereIn('id', $scores->keys())->orderBy('name')->get();
            $leaderboard = $departments->map(fn ($department) => [
                'name' => $department->name,
                'score' => round((float) $scores[$department->id], 2),
            ])->sortByDesc('score')->values();

            $charts[] = $this->bar(
                __('ui.performance'),
                $leaderboard->pluck('name')->all(),
                $leaderboard->pluck('score')->all()
            );
        }

        if ($user->hasRole('finance_dept', 'zone_chief', 'zone_deputy', 'super_admin')) {
            $payrollTotal = (float) Payroll::query()->where('status', 'paid')->sum('net_pay');
            $byDept = Payroll::query()
                ->where('status', 'paid')
                ->selectRaw('department_id, sum(net_pay) as total')
                ->groupBy('department_id')
                ->pluck('total', 'department_id');
            $names = Department::query()->whereIn('id', $byDept->keys())->pluck('name', 'id');
            $charts[] = $this->bar(__('ui.payroll'), $names->values()->all(), $byDept->map(fn ($total) => round((float) $total, 2))->values()->all());
        }

        if ($user->isExecutive() || $user->role === 'super_admin') {
            $eth = EthiopianDateHelper::fromCarbon(now());
            $bounds = EthiopianDateHelper::calendarMonthBounds($eth['year'], $eth['month']);
            $attendance = [
                'present' => HrDailyLog::query()->whereBetween('work_date', [$bounds['start']->toDateString(), $bounds['end']->toDateString()])->where('status', 'present')->count(),
                'absent' => HrDailyLog::query()->whereBetween('work_date', [$bounds['start']->toDateString(), $bounds['end']->toDateString()])->where('status', 'absent')->count(),
                'leave' => HrDailyLog::query()->whereBetween('work_date', [$bounds['start']->toDateString(), $bounds['end']->toDateString()])->where('status', 'leave')->count(),
            ];
        }

        return view('dashboard.index', [
            'counts' => $counts,
            'charts' => array_values(array_filter($charts)),
            'leaderboard' => $leaderboard,
            'payrollTotal' => $payrollTotal,
            'attendance' => $attendance,
            'budgetYear' => $budgetYear,
            'todayLabel' => EthiopianDateHelper::format(now()),
        ]);
    }

    private function bar(string $label, array $labels, array $data): ?array
    {
        if ($labels === []) {
            return null;
        }

        return [
            'title' => $label,
            'config' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [[
                        'label' => $label,
                        'data' => $data,
                        'backgroundColor' => '#1a5c4a',
                        'borderRadius' => 6,
                    ]],
                ],
                'options' => [
                    'responsive' => true,
                    'plugins' => ['legend' => ['display' => false]],
                    'scales' => ['y' => ['beginAtZero' => true]],
                ],
            ],
        ];
    }
}
