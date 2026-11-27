<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index()
    {
        $this->authorize('viewAny', User::class);
        $users = User::query()->with('department')->orderBy('name')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', [
            'account' => new User(['status' => 'active', 'locale' => 'en', 'role' => 'employee']),
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);
        $data = $this->validated($request);
        $account = User::query()->create($data);
        $this->audit->log('user.created', $account, [], $account->only(['name', 'email', 'role', 'department_id', 'status']), $account->department_id);

        return redirect()->route('admin.users.index')->with('status', __('ui.saved'));
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);
        $user->load('department');

        return view('admin.users.show', ['account' => $user]);
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return view('admin.users.form', [
            'account' => $user,
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $before = $user->only(['name', 'email', 'role', 'department_id', 'status', 'base_salary']);
        $user->update($data);
        $this->audit->log('user.updated', $user, $before, $user->only(['name', 'email', 'role', 'department_id', 'status', 'base_salary']), $user->department_id);

        return redirect()->route('admin.users.index')->with('status', __('ui.saved'));
    }

    public function destroy(User $user)
    {
        $this->authorize('update', $user);
        abort_if((int) $user->id === (int) auth()->id(), 422);
        $user->update(['status' => 'inactive']);
        $this->audit->log('user.deactivated', $user, [], ['status' => 'inactive'], $user->department_id);

        return back()->with('status', __('ui.deactivated'));
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', Password::defaults()],
            'role' => ['required', Rule::in(config('workflow.roles'))],
            'department_id' => ['nullable', 'exists:departments,id'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'locale' => ['required', 'in:en,am'],
        ]);

        if (in_array($data['role'], config('workflow.department_roles'), true) && empty($data['department_id'])) {
            throw ValidationException::withMessages([
                'department_id' => __('ui.department_required'),
            ]);
        }

        $data['base_salary'] = $data['base_salary'] ?? 0;

        return $data;
    }
}
