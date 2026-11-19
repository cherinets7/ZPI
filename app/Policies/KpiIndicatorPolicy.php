<?php

namespace App\Policies;

use App\Models\KpiIndicator;
use App\Models\User;

class KpiIndicatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, KpiIndicator $kpi): bool
    {
        if ($user->seesAllDepartments() || $user->role === 'super_admin') {
            return true;
        }

        return (int) $user->department_id === (int) $kpi->department_id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'super_admin';
    }

    public function update(User $user, KpiIndicator $kpi): bool
    {
        return $user->role === 'super_admin';
    }

    public function delete(User $user, KpiIndicator $kpi): bool
    {
        return $user->role === 'super_admin';
    }
}
