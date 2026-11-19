<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'super_admin';
    }

    public function view(User $user, User $model): bool
    {
        return $user->role === 'super_admin' || (int) $user->id === (int) $model->id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'super_admin';
    }

    public function update(User $user, User $model): bool
    {
        return $user->role === 'super_admin';
    }

    public function registerEmployee(User $user): bool
    {
        return $user->role === 'hr_specialist' && (bool) $user->department_id;
    }

    public function transfer(User $user, User $model): bool
    {
        if ($model->role !== 'employee') {
            return false;
        }

        if ($user->role === 'super_admin') {
            return true;
        }

        return $user->role === 'hr_specialist'
            && (int) $user->department_id === (int) $model->department_id;
    }
}
