<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\HrDailyLog;
use App\Models\HrWeeklySummary;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\AuditService;
use App\Support\EthiopianDateHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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
            'canEdit' => $request->user()->role === 'hr_specialist'
                && (int) $request->user()->department_id === (int) $department->id
                && ! HrWeeklySummary::closesAttendance((int) $department->id, $week['start']),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', HrDailyLog::class);

        return view('hr.attendance.form', [
            'log' => new HrDailyLog(['status' => 'present', 'hours' => 8]),
            'employees' => $this->employees((int) $request->user()->department_id),
        ]);
    }

    public function storeEntry(Request $request)
    {
        $this->authorize('create', HrDailyLog::class);
        $data = $this->entryInput($request);
        $employee = $this->employeeInDepartment((int) $request->user()->department_id, (int) $data['user_id']);
        $date = Carbon::parse($data['work_date']);
        $this->guardOpenWeek((int) $request->user()->department_id, $date);
        $log = $this->persist($request, $employee, $date, $data['status'], (float) $data['hours'], $data['note'] ?? null);

        return redirect()->route('hr.attendance.show', $log)->with('status', __('ui.saved'));
    }

    public function show(HrDailyLog $log)
    {
        $this->authorize('view', $log);
        $log->load(['user', 'department', 'recorder']);

        return view('hr.attendance.show', compact('log'));
    }

    public function edit(HrDailyLog $log)
    {
        $this->authorize('update', $log);
        $log->load('user');

        return view('hr.attendance.form', [
            'log' => $log,
            'employees' => $this->employees((int) $log->department_id),
        ]);
    }

    public function update(Request $request, HrDailyLog $log)
    {
        $this->authorize('update', $log);
        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,field_work'],
            'hours' => ['required', 'numeric', 'between:0,24'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $date = Carbon::parse($data['work_date']);
        $this->guardOpenWeek((int) $log->department_id, $date);
        $log->load('user');
        $saved = $this->persist($request, $log->user, $date, $data['status'], (float) $data['hours'], $data['note'] ?? null, $log);

        return redirect()->route('hr.attendance.show', $saved)->with('status', __('ui.saved'));
    }

    public function destroy(HrDailyLog $log)
    {
        $this->authorize('delete', $log);
        $departmentId = $log->department_id;
        $before = $log->only(['user_id', 'work_date', 'status', 'hours']);
        $log->delete();
        $this->audit->log('attendance.deleted', null, $before, [], $departmentId);

        return redirect()->route('hr.attendance.index')->with('status', __('ui.deleted'));
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
        $firstDate = Carbon::parse($data['rows'][0]['work_date']);
        if (HrWeeklySummary::closesAttendance($departmentId, $firstDate)) {
            return back()->withErrors(['rows' => __('ui.closed_week')]);
        }

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

            $this->persist($request, $employee, $date, $row['status'], (float) $row['hours'], $row['note'] ?? null);
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

    private function employees(int $departmentId)
    {
        return User::query()
            ->where('department_id', $departmentId)
            ->where('role', 'employee')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function employeeInDepartment(int $departmentId, int $userId): User
    {
        $employee = User::query()
            ->where('department_id', $departmentId)
            ->where('role', 'employee')
            ->where('status', 'active')
            ->find($userId);
        abort_unless($employee, 403);

        return $employee;
    }

    /**
     * @return array{user_id: int, work_date: string, status: string, hours: float|int|string, note: ?string}
     */
    private function entryInput(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,field_work'],
            'hours' => ['required', 'numeric', 'between:0,24'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function guardOpenWeek(int $departmentId, Carbon $date): void
    {
        if (! EthiopianDateHelper::isWorkingDay($date)) {
            throw ValidationException::withMessages(['work_date' => __('ui.not_working_day')]);
        }

        if (HrWeeklySummary::closesAttendance($departmentId, $date)) {
            throw ValidationException::withMessages(['work_date' => __('ui.closed_week')]);
        }
    }

    private function persist(Request $request, User $employee, Carbon $date, string $status, float $hours, ?string $note, ?HrDailyLog $existing = null): HrDailyLog
    {
        if (! EthiopianDateHelper::isWorkingDay($date)) {
            throw ValidationException::withMessages(['work_date' => __('ui.not_working_day')]);
        }

        $classified = $this->attendance->classifyDay($employee, $date);
        if (in_array($classified['status'], ['holiday', 'leave'], true)) {
            $status = $classified['status'];
            $hours = 0;
        } elseif ($this->approvedFieldWork($employee, $date)) {
            $status = 'field_work';
            $hours = (float) Setting::getValue('hours_per_day', 8);
        } elseif ($status === 'absent') {
            $hours = 0;
        }

        $eth = EthiopianDateHelper::fromCarbon($date);
        $payload = [
            'department_id' => $employee->department_id,
            'eth_year' => $eth['year'],
            'eth_month' => $eth['month'],
            'eth_day' => $eth['day'],
            'status' => $status,
            'hours' => $hours,
            'note' => $note,
            'recorded_by' => $request->user()->id,
        ];

        $current = HrDailyLog::query()
            ->where('user_id', $employee->id)
            ->whereDate('work_date', $date->toDateString())
            ->first();

        if ($existing && (! $current || (int) $current->id !== (int) $existing->id)) {
            $existing->delete();
        }

        if ($current) {
            $current->update($payload);

            return $current->refresh();
        }

        return HrDailyLog::query()->create($payload + [
            'user_id' => $employee->id,
            'work_date' => $date->toDateString(),
        ]);
    }

    private function approvedFieldWork(User $employee, Carbon $date): bool
    {
        return LeaveRequest::query()
            ->where('user_id', $employee->id)
            ->where('status', 'approved')
            ->where('type', 'field_work')
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->exists();
    }
}
