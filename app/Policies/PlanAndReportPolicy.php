<?php

namespace App\Policies;

use App\Models\PlanAndReport;
use App\Models\User;

class PlanAndReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(
            'employee',
            'dev_plan_specialist',
            'dept_head',
            'plan_dept',
            'zone_chief',
            'zone_deputy',
            'super_admin'
        ) || $user->isActingHead();
    }

    public function view(User $user, PlanAndReport $plan): bool
    {
        if ($user->hasRole('plan_dept', 'zone_chief', 'zone_deputy', 'super_admin')) {
            return true;
        }

        if ((int) $plan->user_id === (int) $user->id) {
            return true;
        }

        if ($user->hasRole('dev_plan_specialist') && (int) $user->department_id === (int) $plan->department_id) {
            return true;
        }

        return $user->managesDepartment($plan->department_id);
    }

    public function create(User $user): bool
    {
        return $user->role === 'employee' && $user->department_id && $user->department?->is_active;
    }

    public function update(User $user, PlanAndReport $plan): bool
    {
        return $this->ownsEditable($user, $plan);
    }

    public function delete(User $user, PlanAndReport $plan): bool
    {
        return (int) $plan->user_id === (int) $user->id
            && $plan->status === 'draft'
            && ! $plan->isLocked()
            && ! $plan->is_rollup;
    }

    public function submit(User $user, PlanAndReport $plan): bool
    {
        return (int) $plan->user_id === (int) $user->id
            && $plan->status === 'draft'
            && ! $plan->isLocked()
            && ! $plan->is_rollup;
    }

    public function resubmit(User $user, PlanAndReport $plan): bool
    {
        return (int) $plan->user_id === (int) $user->id
            && $plan->status === 'rejected'
            && ! $plan->isLocked()
            && ! $plan->is_rollup;
    }

    public function screen(User $user, PlanAndReport $plan): bool
    {
        return $user->role === 'dev_plan_specialist'
            && (int) $user->department_id === (int) $plan->department_id
            && $plan->status === 'submitted'
            && $plan->period_type !== 'daily'
            && ! $plan->isLocked()
            && ! $plan->is_rollup;
    }

    public function approve(User $user, PlanAndReport $plan): bool
    {
        if ($plan->isLocked() || $plan->is_rollup || (int) $plan->user_id === (int) $user->id) {
            return false;
        }

        if (! $user->managesDepartment($plan->department_id)) {
            return false;
        }

        if ($plan->period_type === 'daily') {
            return $plan->status === 'submitted';
        }

        return $plan->status === 'verified';
    }

    public function reject(User $user, PlanAndReport $plan): bool
    {
        if ($plan->isLocked() || $plan->is_rollup || $plan->status === 'approved') {
            return false;
        }

        if ($user->role === 'dev_plan_specialist'
            && (int) $user->department_id === (int) $plan->department_id
            && $plan->status === 'submitted'
            && $plan->period_type !== 'daily') {
            return true;
        }

        return $user->managesDepartment($plan->department_id)
            && (int) $plan->user_id !== (int) $user->id
            && in_array($plan->status, ['verified', 'submitted'], true);
    }

    public function unlock(User $user, PlanAndReport $plan): bool
    {
        if (! $plan->isLocked()) {
            return false;
        }

        if ($user->role === 'super_admin') {
            return true;
        }

        return $user->role === 'dept_head' && $user->managesDepartment($plan->department_id);
    }

    public function feedback(User $user, PlanAndReport $plan): bool
    {
        if (! $this->view($user, $plan)) {
            return false;
        }

        return $user->hasRole('dept_head', 'plan_dept', 'zone_chief', 'zone_deputy', 'dev_plan_specialist', 'super_admin')
            || $user->managesDepartment($plan->department_id);
    }

    public function export(User $user): bool
    {
        return $user->hasRole('plan_dept', 'zone_chief', 'zone_deputy', 'super_admin', 'dept_head', 'dev_plan_specialist')
            || $user->isActingHead();
    }

    private function ownsEditable(User $user, PlanAndReport $plan): bool
    {
        return (int) $plan->user_id === (int) $user->id
            && in_array($plan->status, ['draft', 'rejected'], true)
            && ! $plan->isLocked()
            && ! $plan->is_rollup;
    }
}
