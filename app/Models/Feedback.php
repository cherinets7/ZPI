<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $fillable = [
        'plan_and_report_id',
        'department_id',
        'sender_id',
        'receiver_id',
        'kind',
        'comment',
        'action_taken',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanAndReport::class, 'plan_and_report_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('plan_dept', 'zone_chief', 'zone_deputy', 'super_admin')) {
            return $query;
        }

        if ($user->hasRole('dev_plan_specialist') || $user->managesDepartment($user->department)) {
            return $query->where('department_id', $user->department_id);
        }

        return $query->where(function (Builder $inner) use ($user) {
            $inner->where('receiver_id', $user->id)->orWhere('sender_id', $user->id);
        });
    }
}
