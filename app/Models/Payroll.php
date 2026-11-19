<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'eth_year',
        'eth_month',
        'base_salary',
        'performance_score',
        'attendance_ratio',
        'bonus_or_adjustment',
        'net_pay',
        'status',
        'paid_by',
        'paid_at',
        'verification_hash',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'performance_score' => 'decimal:2',
            'attendance_ratio' => 'decimal:4',
            'bonus_or_adjustment' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'paid_at' => 'datetime',
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

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('finance_dept', 'zone_chief', 'zone_deputy', 'super_admin')) {
            return $query;
        }

        if ($user->hasRole('hr_specialist', 'dept_head') || $user->managesDepartment($user->department)) {
            return $query->where('department_id', $user->department_id);
        }

        return $query->where('user_id', $user->id);
    }
}
