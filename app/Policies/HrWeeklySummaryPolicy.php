<?php

namespace App\Policies;

use App\Models\HrWeeklySummary;
use App\Models\User;

class HrWeeklySummaryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('hr_specialist', 'dept_head', 'zone_chief', 'zone_deputy', 'super_admin', 'finance_dept')
            || $user->isActingHead();
    }

    public function view(User $user, HrWeeklySummary $summary): bool
    {
        if ($user->hasRole('zone_chief', 'zone_deputy', 'super_admin', 'finance_dept')) {
            return true;
        }

        return (int) $user->department_id === (int) $summary->department_id
            && ($user->hasRole('hr_specialist', 'dept_head') || $user->managesDepartment($summary->department_id));
    }

    public function prepare(User $user): bool
    {
        return $user->role === 'hr_specialist' && (bool) $user->department_id;
    }

    public function verify(User $user, HrWeeklySummary $summary): bool
    {
        return $user->role === 'hr_specialist'
            && (int) $user->department_id === (int) $summary->department_id
            && in_array($summary->status, ['draft', 'rejected'], true);
    }

    public function forward(User $user, HrWeeklySummary $summary): bool
    {
        return $user->role === 'hr_specialist'
            && (int) $user->department_id === (int) $summary->department_id
            && $summary->status === 'verified';
    }

    public function decide(User $user, HrWeeklySummary $summary): bool
    {
        return $user->managesDepartment($summary->department_id) && $summary->status === 'forwarded';
    }
}
