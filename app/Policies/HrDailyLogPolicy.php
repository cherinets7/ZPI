<?php

namespace App\Policies;

use App\Models\HrDailyLog;
use App\Models\HrWeeklySummary;
use App\Models\User;

class HrDailyLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('hr_specialist', 'dept_head', 'finance_dept', 'zone_chief', 'zone_deputy', 'super_admin')
            || $user->isActingHead();
    }

    public function view(User $user, HrDailyLog $log): bool
    {
        if ($user->hasRole('finance_dept', 'zone_chief', 'zone_deputy', 'super_admin')) {
            return true;
        }

        return (int) $user->department_id === (int) $log->department_id
            && ($user->hasRole('hr_specialist', 'dept_head') || $user->managesDepartment($log->department_id));
    }

    public function create(User $user): bool
    {
        return $user->role === 'hr_specialist' && (bool) $user->department_id;
    }

    public function update(User $user, HrDailyLog $log): bool
    {
        return $this->writes($user, $log);
    }

    public function delete(User $user, HrDailyLog $log): bool
    {
        return $this->writes($user, $log);
    }

    private function writes(User $user, HrDailyLog $log): bool
    {
        if ($user->role !== 'hr_specialist' || (int) $user->department_id !== (int) $log->department_id) {
            return false;
        }

        return ! HrWeeklySummary::closesAttendance((int) $log->department_id, $log->work_date);
    }
}
