<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, LeaveRequest $leave): bool
    {
        if ($user->hasRole('zone_chief', 'zone_deputy', 'super_admin', 'plan_dept')) {
            return true;
        }

        if ((int) $leave->user_id === (int) $user->id) {
            return true;
        }

        if ($user->hasRole('hr_specialist') && (int) $user->department_id === (int) $leave->department_id) {
            return true;
        }

        return $user->managesDepartment($leave->department_id);
    }

    public function create(User $user): bool
    {
        return $user->role === 'employee' && (bool) $user->department_id;
    }

    public function update(User $user, LeaveRequest $leave): bool
    {
        return (int) $leave->user_id === (int) $user->id && in_array($leave->status, ['draft', 'rejected'], true);
    }

    public function delete(User $user, LeaveRequest $leave): bool
    {
        return (int) $leave->user_id === (int) $user->id && $leave->status === 'draft';
    }

    public function submit(User $user, LeaveRequest $leave): bool
    {
        return (int) $leave->user_id === (int) $user->id && in_array($leave->status, ['draft', 'rejected'], true);
    }

    public function decide(User $user, LeaveRequest $leave): bool
    {
        return $user->managesDepartment($leave->department_id)
            && (int) $leave->user_id !== (int) $user->id
            && $leave->status === 'submitted';
    }
}
