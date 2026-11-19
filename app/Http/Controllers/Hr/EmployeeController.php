<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Imports\EmployeesImport;
use App\Models\Department;
use App\Models\EmployeeTransfer;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('registerEmployee', User::class);
        $employees = User::query()
            ->where('department_id', $request->user()->department_id)
            ->where('role', 'employee')
            ->orderBy('name')
            ->paginate(20);

        return view('hr.employees.index', compact('employees'));
    }

    public function create()
    {
        $this->authorize('registerEmployee', User::class);

        return view('hr.employees.form', ['employee' => new User(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        $this->authorize('registerEmployee', User::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $employee = User::query()->create([
            ...$data,
            'role' => 'employee',
            'department_id' => $request->user()->department_id,
            'locale' => 'en',
        ]);
        $this->audit->log('employee.registered', $employee, [], [
            'name' => $employee->name,
            'base_salary' => $employee->base_salary,
            'department_id' => $employee->department_id,
        ], $employee->department_id);

        return redirect()->route('hr.employees.index')->with('status', __('ui.saved'));
    }

    public function edit(Request $request, User $employee)
    {
        $this->authorize('registerEmployee', User::class);
        abort_unless((int) $employee->department_id === (int) $request->user()->department_id && $employee->role === 'employee', 403);

        return view('hr.employees.form', compact('employee'));
    }

    public function update(Request $request, User $employee)
    {
        $this->authorize('registerEmployee', User::class);
        abort_unless((int) $employee->department_id === (int) $request->user()->department_id && $employee->role === 'employee', 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->id)],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => ['nullable', Password::defaults()],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }
        $before = $employee->only(['name', 'email', 'base_salary', 'status']);
        $employee->update($data);
        $this->audit->log('employee.updated', $employee, $before, $employee->only(['name', 'email', 'base_salary', 'status']), $employee->department_id);

        return redirect()->route('hr.employees.index')->with('status', __('ui.saved'));
    }

    public function transferForm(User $employee)
    {
        $this->authorize('transfer', $employee);

        return view('hr.employees.transfer', [
            'employee' => $employee->load('department'),
            'departments' => Department::query()->where('is_active', true)->where('id', '!=', $employee->department_id)->orderBy('name')->get(),
        ]);
    }

    public function transfer(Request $request, User $employee)
    {
        $this->authorize('transfer', $employee);
        $data = $request->validate([
            'to_department_id' => ['required', 'exists:departments,id'],
            'effective_on' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $destination = Department::query()->findOrFail($data['to_department_id']);
        abort_unless($destination->is_active, 422);
        abort_if((int) $destination->id === (int) $employee->department_id, 422);

        DB::transaction(function () use ($employee, $destination, $data, $request) {
            $from = $employee->department_id;
            EmployeeTransfer::query()->create([
                'user_id' => $employee->id,
                'from_department_id' => $from,
                'to_department_id' => $destination->id,
                'transferred_by' => $request->user()->id,
                'effective_on' => $data['effective_on'],
                'reason' => $data['reason'],
            ]);
            $employee->update(['department_id' => $destination->id]);
            $this->audit->log('employee.transferred', $employee, ['department_id' => $from], [
                'department_id' => $destination->id,
                'reason' => $data['reason'],
            ], $from);
        });

        return redirect()->route('hr.employees.index')->with('status', __('ui.transferred_kept_history'));
    }

    public function import(Request $request)
    {
        $this->authorize('registerEmployee', User::class);
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        Excel::import(new EmployeesImport($request->user()->department, $request->user()), $request->file('file'));

        return back()->with('status', __('ui.saved'));
    }
}
