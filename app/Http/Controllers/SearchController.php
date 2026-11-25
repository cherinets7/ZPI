<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Holiday;
use App\Models\KpiIndicator;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PlanAndReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $term = mb_substr($term, 0, 80);
        $groups = mb_strlen($term) >= 2 ? $this->groups($request->user(), $term) : [];

        if ($request->expectsJson()) {
            return response()->json(['groups' => $groups]);
        }

        return view('search.index', [
            'term' => $term,
            'groups' => $groups,
        ]);
    }

    /**
     * @return list<array{label: string, items: list<array{title: string, meta: string, url: string}>}>
     */
    private function groups(User $user, string $term): array
    {
        $like = '%'.addcslashes($term, '%_\\').'%';
        $groups = [];

        if ($user->can('viewAny', PlanAndReport::class)) {
            $groups[] = $this->group(__('ui.plans_reports'), PlanAndReport::query()
                ->visibleTo($user)
                ->where(function (Builder $query) use ($like) {
                    $query->where('title', 'like', $like)->orWhere('notes', 'like', $like);
                })
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (PlanAndReport $plan) => [
                    'title' => $plan->title,
                    'meta' => __('ui.'.$plan->status),
                    'url' => route('plans.show', $plan),
                ])->all());
        }

        if ($user->can('viewAny', LeaveRequest::class)) {
            $groups[] = $this->group(__('ui.leave_field'), LeaveRequest::query()
                ->visibleTo($user)
                ->with('user')
                ->where(function (Builder $query) use ($like) {
                    $query->where('reason', 'like', $like)
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', $like));
                })
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (LeaveRequest $leave) => [
                    'title' => ($leave->user?->name ?: __('ui.leave_field')).' — '.__('ui.'.$leave->type),
                    'meta' => __('ui.'.$leave->status),
                    'url' => route('leaves.show', $leave),
                ])->all());
        }

        if ($user->can('viewAny', Payroll::class)) {
            $groups[] = $this->group(__('ui.payroll'), Payroll::query()
                ->visibleTo($user)
                ->with('user')
                ->whereHas('user', fn (Builder $query) => $query->where('name', 'like', $like))
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Payroll $payroll) => [
                    'title' => $payroll->user?->name ?: __('ui.payroll'),
                    'meta' => __('ui.'.$payroll->status),
                    'url' => route('payrolls.show', $payroll),
                ])->all());
        }

        if ($user->role === 'super_admin' || ($user->role === 'hr_specialist' && $user->department_id)) {
            $people = User::query()
                ->where(function (Builder $query) use ($like) {
                    $query->where('name', 'like', $like)->orWhere('email', 'like', $like);
                })
                ->orderBy('name')
                ->limit(8);
            if ($user->role !== 'super_admin') {
                $people->where('department_id', $user->department_id)->where('role', 'employee');
            }
            $groups[] = $this->group(__('ui.employees'), $people->get()->map(fn (User $person) => [
                'title' => $person->name,
                'meta' => $person->email,
                'url' => $user->role === 'super_admin'
                    ? route('admin.users.edit', $person)
                    : route('hr.employees.edit', $person),
            ])->all());
        }

        if ($user->can('viewAny', KpiIndicator::class)) {
            $kpis = KpiIndicator::query()->where('title', 'like', $like)->orderBy('title')->limit(8);
            if (! $user->seesAllDepartments() && $user->role !== 'super_admin') {
                $kpis->where('department_id', $user->department_id);
            }
            $groups[] = $this->group(__('ui.kpis'), $kpis->get()->map(fn (KpiIndicator $kpi) => [
                'title' => $kpi->title,
                'meta' => $kpi->unit,
                'url' => $user->can('update', $kpi) ? route('kpis.edit', $kpi) : route('kpis.index', ['department_id' => $kpi->department_id]),
            ])->all());
        }

        if ($user->can('viewAny', Department::class)) {
            $departments = Department::query()
                ->where(function (Builder $query) use ($like) {
                    $query->where('name', 'like', $like)->orWhere('code', 'like', $like);
                })
                ->orderBy('name')
                ->limit(8);
            if (! $user->hasRole('super_admin', 'plan_dept', 'zone_chief', 'zone_deputy')) {
                $departments->where('id', $user->department_id);
            }
            $groups[] = $this->group(__('ui.departments'), $departments->get()->map(fn (Department $department) => [
                'title' => $department->name,
                'meta' => $department->code,
                'url' => route('departments.show', $department),
            ])->all());
        }

        if ($user->can('viewAny', Holiday::class)) {
            $groups[] = $this->group(__('ui.holidays'), Holiday::query()
                ->where(function (Builder $query) use ($like) {
                    $query->where('name', 'like', $like)->orWhere('name_am', 'like', $like);
                })
                ->orderByDesc('eth_year')
                ->limit(8)
                ->get()
                ->map(fn (Holiday $holiday) => [
                    'title' => app()->getLocale() === 'am' && $holiday->name_am ? $holiday->name_am : $holiday->name,
                    'meta' => (string) $holiday->eth_year,
                    'url' => $user->can('update', $holiday) ? route('holidays.edit', $holiday) : route('holidays.index'),
                ])->all());
        }

        $groups[] = $this->group(__('ui.notifications'), $user->notifications()
            ->where('data', 'like', $like)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($notification) => [
                'title' => (string) ($notification->data['title'] ?? __('ui.notifications')),
                'meta' => (string) ($notification->data['body'] ?? ''),
                'url' => route('notifications.index'),
            ])->all());

        return array_values(array_filter($groups, fn (array $group) => $group['items'] !== []));
    }

    /**
     * @param  list<array{title: string, meta: string, url: string}>  $items
     * @return array{label: string, items: list<array{title: string, meta: string, url: string}>}
     */
    private function group(string $label, mixed $items): array
    {
        return [
            'label' => $label,
            'items' => array_values(is_array($items) ? $items : $items->all()),
        ];
    }
}
