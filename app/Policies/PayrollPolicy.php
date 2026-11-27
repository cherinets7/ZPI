<?php

namespace App\Policies;

use App\Models\Payroll;
use App\Models\User;

class PayrollPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('employee', 'finance_dept', 'zone_chief', 'zone_deputy', 'super_admin', 'hr_specialist', 'dept_head')
            || $user->isActingHead();
    }

    public function view(User $user, Payroll $payroll): bool
    {
        if ($user->hasRole('finance_dept', 'zone_chief', 'zone_deputy', 'super_admin')) {
            return true;
        }

        if ((int) $payroll->user_id === (int) $user->id) {
            return true;
        }

        return (int) $user->department_id === (int) $payroll->department_id
            && ($user->hasRole('hr_specialist', 'dept_head') || $user->managesDepartment($payroll->department_id));
    }

    public function create(User $user): bool
    {
        return $user->role === 'finance_dept';
    }

    public function pay(User $user, Payroll $payroll): bool
    {
        return $user->role === 'finance_dept' && $payroll->status === 'calculated';
    }

    public function update(User $user, Payroll $payroll): bool
    {
        return $this->openForFinance($user, $payroll);
    }

    public function delete(User $user, Payroll $payroll): bool
    {
        return $this->openForFinance($user, $payroll);
    }

    private function openForFinance(User $user, Payroll $payroll): bool
    {
        return $user->role === 'finance_dept'
            && $payroll->paid_at === null
            && $payroll->status !== 'paid';
    }
}
