<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Department::class);
        $query = Department::query()->with(['head', 'actingHead'])->orderBy('name');
        if (! $request->user()->hasRole('super_admin', 'plan_dept', 'zone_chief', 'zone_deputy')) {
            $query->where('id', $request->user()->department_id);
        }

        return view('departments.index', ['departments' => $query->paginate(20)]);
    }

    public function create()
    {
        $this->authorize('create', Department::class);

        return view('departments.form', [
            'department' => new Department(['is_active' => true]),
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Department::class);
        $data = $this->validated($request);
        $department = Department::query()->create($data);
        $this->syncHeadRole($department);
        $this->audit->log('department.created', $department, [], $department->toArray(), $department->id);

        return redirect()->route('departments.show', $department)->with('status', __('ui.saved'));
    }

    public function show(Department $department)
    {
        $this->authorize('view', $department);
        $department->load(['head', 'actingHead', 'users']);

        return view('departments.show', [
            'department' => $department,
            'candidates' => User::query()->where('department_id', $department->id)->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function edit(Department $department)
    {
        $this->authorize('update', $department);

        return view('departments.form', [
            'department' => $department,
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Department $department)
    {
        $this->authorize('update', $department);
        $before = $department->toArray();
        $department->update($this->validated($request, $department));
        $this->syncHeadRole($department);
        $this->audit->log('department.updated', $department, $before, $department->toArray(), $department->id);

        return redirect()->route('departments.show', $department)->with('status', __('ui.saved'));
    }

    public function destroy(Department $department)
    {
        $this->authorize('delete', $department);
        $department->update(['is_active' => false]);
        $this->audit->log('department.deactivated', $department, [], ['is_active' => false], $department->id);

        return redirect()->route('departments.index')->with('status', __('ui.deactivated'));
    }

    public function acting(Request $request, Department $department)
    {
        $this->authorize('delegate', $department);
        $data = $request->validate([
            'acting_head_id' => ['nullable', 'exists:users,id'],
            'acting_until' => ['nullable', 'date', 'required_with:acting_head_id', 'after_or_equal:today'],
        ]);

        if (! empty($data['acting_head_id'])) {
            $acting = User::query()->findOrFail($data['acting_head_id']);
            abort_unless((int) $acting->department_id === (int) $department->id && $acting->status === 'active', 422);
        } else {
            $data['acting_until'] = null;
        }

        $department->update([
            'acting_head_id' => $data['acting_head_id'] ?: null,
            'acting_until' => $data['acting_until'] ?? null,
        ]);
        $this->audit->log('department.acting_head', $department, [], $data, $department->id);

        return back()->with('status', __('ui.saved'));
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($department?->id)],
            'head_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function syncHeadRole(Department $department): void
    {
        if (! $department->head_id) {
            return;
        }

        User::query()->whereKey($department->head_id)->update([
            'role' => 'dept_head',
            'department_id' => $department->id,
        ]);
    }
}
