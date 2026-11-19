<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin', 'plan_dept', 'zone_chief', 'zone_deputy', 'dept_head') || $user->isActingHead();
    }

    public function view(User $user, Department $department): bool
    {
        if ($user->hasRole('super_admin', 'plan_dept', 'zone_chief', 'zone_deputy')) {
            return true;
        }

        return (int) $user->department_id === (int) $department->id
            && ($user->role === 'dept_head' || $user->managesDepartment($department));
    }

    public function create(User $user): bool
    {
        return $user->role === 'super_admin';
    }

    public function update(User $user, Department $department): bool
    {
        return $user->role === 'super_admin';
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->role === 'super_admin';
    }

    public function delegate(User $user, Department $department): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        return $user->role === 'dept_head' && (int) $department->head_id === (int) $user->id;
    }
}
