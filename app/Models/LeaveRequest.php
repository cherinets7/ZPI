<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'type',
        'start_date',
        'end_date',
        'days',
        'reason',
        'status',
        'reviewer_id',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'decimal:1',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('zone_chief', 'zone_deputy', 'super_admin', 'plan_dept')) {
            return $query;
        }

        if ($user->managesDepartment($user->department) || $user->hasRole('hr_specialist')) {
            return $query->where('department_id', $user->department_id);
        }

        return $query->where('user_id', $user->id);
    }
}
