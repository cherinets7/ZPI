<?php

namespace App\Policies;

use App\Models\Feedback;
use App\Models\User;

class FeedbackPolicy
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

    public function view(User $user, Feedback $feedback): bool
    {
        if ($user->hasRole('plan_dept', 'zone_chief', 'zone_deputy', 'super_admin')) {
            return true;
        }

        if ((int) $user->department_id !== (int) $feedback->department_id) {
            return false;
        }

        if ((int) $feedback->sender_id === (int) $user->id || (int) $feedback->receiver_id === (int) $user->id) {
            return true;
        }

        return $user->hasRole('dev_plan_specialist', 'dept_head') || $user->managesDepartment($feedback->department_id);
    }

    public function update(User $user, Feedback $feedback): bool
    {
        if ((int) $feedback->receiver_id === (int) $user->id && $this->view($user, $feedback)) {
            return true;
        }

        return $this->canRevise($user, $feedback);
    }

    public function delete(User $user, Feedback $feedback): bool
    {
        if ($user->role === 'employee') {
            return false;
        }

        return $this->canRevise($user, $feedback);
    }

    private function canRevise(User $user, Feedback $feedback): bool
    {
        if ($user->role === 'employee' || (int) $feedback->sender_id !== (int) $user->id) {
            return false;
        }

        if (! $this->view($user, $feedback)) {
            return false;
        }

        $plan = $feedback->plan;
        if ($plan && ($plan->status === 'approved' || $plan->isLocked())) {
            return false;
        }

        return true;
    }
}
