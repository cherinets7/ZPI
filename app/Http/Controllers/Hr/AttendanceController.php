<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\HrDailyLog;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\AuditService;
use App\Support\EthiopianDateHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('hr_specialist', 'dept_head', 'finance_dept', 'zone_chief', 'zone_deputy', 'super_admin') || $request->user()->isActingHead(), 403);

        $department = $this->department($request);
        $anchor = $request->filled('date') ? Carbon::parse($request->string('date')) : now();
        $week = EthiopianDateHelper::weekIdentity($anchor);
        $days = [];
        $cursor = $week['start']->copy();
        while ($cursor->lte($week['end'])) {
            if (EthiopianDateHelper::isWorkingDay($cursor)) {
                $days[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        $employees = User::query()
            ->where('department_id', $department->id)
            ->where('role', 'employee')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $logs = HrDailyLog::query()
            ->whereIn('user_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$week['start']->toDateString(), $week['end']->toDateString()])
            ->get()
            ->groupBy(fn (HrDailyLog $log) => $log->user_id.'|'.$log->work_date->toDateString());

        $holidays = Holiday::query()
            ->whereBetween('gregorian_date', [$week['start']->toDateString(), $week['end']->toDateString()])
            ->get()
            ->keyBy(fn ($holiday) => $holiday->gregorian_date->toDateString());

        $leaves = LeaveRequest::query()
            ->whereIn('user_id', $employees->pluck('id'))
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $week['end']->toDateString())
            ->whereDate('end_date', '>=', $week['start']->toDateString())
            ->get();

        return view('hr.attendance.index', [
            'department' => $department,
            'departments' => $request->user()->seesAllDepartments() || $request->user()->role === 'finance_dept'
                ? Department::query()->where('is_active', true)->orderBy('name')->get()
                : collect([$department]),
            'week' => $week,
            'days' => $days,
            'employees' => $employees,
            'logs' => $logs,
            'holidays' => $holidays,
            'leaves' => $leaves,
            'canEdit' => $request->user()->role === 'hr_specialist' && (int) $request->user()->department_id === (int) $department->id,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->role === 'hr_specialist', 403);
        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.user_id' => ['required', 'integer'],
            'rows.*.work_date' => ['required', 'date'],
            'rows.*.status' => ['required', 'in:present,absent,field_work'],
            'rows.*.hours' => ['required', 'numeric', 'between:0,24'],
            'rows.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $departmentId = (int) $request->user()->department_id;
        foreach ($data['rows'] as $row) {
            $employee = User::query()
                ->where('department_id', $departmentId)
                ->where('role', 'employee')
                ->find($row['user_id']);
            if (! $employee) {
                continue;
            }

            $date = Carbon::parse($row['work_date']);
            if (! EthiopianDateHelper::isWorkingDay($date)) {
                continue;
            }

            $classified = $this->attendance->classifyDay($employee, $date);
            $status = $row['status'];
            $hours = $row['hours'];
            if (in_array($classified['status'], ['holiday', 'leave'], true)) {
                $status = $classified['status'];
                $hours = 0;
            } elseif ($classified['status'] === 'field_work') {
                $status = 'field_work';
                $hours = (float) \App\Models\Setting::getValue('hours_per_day', 8);
            } elseif ($status === 'absent') {
                $hours = 0;
            }

            $eth = EthiopianDateHelper::fromCarbon($date);
            HrDailyLog::query()->updateOrCreate(
                ['user_id' => $employee->id, 'work_date' => $date->toDateString()],
                [
                    'department_id' => $departmentId,
                    'eth_year' => $eth['year'],
                    'eth_month' => $eth['month'],
                    'eth_day' => $eth['day'],
                    'status' => $status,
                    'hours' => $hours,
                    'note' => $row['note'] ?? null,
                    'recorded_by' => $request->user()->id,
                ]
            );
        }

        $this->audit->log('attendance.recorded', null, [], ['rows' => count($data['rows'])], $departmentId);

        return back()->with('status', __('ui.saved'));
    }

    private function department(Request $request): Department
    {
        if (($request->user()->seesAllDepartments() || $request->user()->hasRole('finance_dept')) && $request->filled('department_id')) {
            return Department::query()->findOrFail($request->integer('department_id'));
        }

        abort_unless($request->user()->department_id, 403);

        return $request->user()->department;
    }
}
