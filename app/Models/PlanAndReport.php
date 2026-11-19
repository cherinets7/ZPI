<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanAndReport extends Model
{
    protected $table = 'plans_and_reports';

    protected $fillable = [
        'user_id',
        'department_id',
        'kpi_indicator_id',
        'type',
        'period_type',
        'eth_year',
        'eth_period',
        'title',
        'target_value',
        'achieved_value',
        'performance_score',
        'status',
        'locked_at',
        'period_start',
        'period_end',
        'is_rollup',
        'notes',
        'approved_by',
        'submitted_at',
        'approved_at',
        'verification_hash',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'achieved_value' => 'decimal:2',
            'performance_score' => 'decimal:2',
            'locked_at' => 'datetime',
            'period_start' => 'date',
            'period_end' => 'date',
            'is_rollup' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function kpiIndicator(): BelongsTo
    {
        return $this->belongsTo(KpiIndicator::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('plan_dept', 'zone_chief', 'zone_deputy', 'super_admin')) {
            return $query;
        }

        if ($user->hasRole('dev_plan_specialist', 'hr_specialist')) {
            return $query->where('department_id', $user->department_id);
        }

        if ($user->managesDepartment($user->department)) {
            return $query->where('department_id', $user->department_id);
        }

        return $query->where('user_id', $user->id);
    }
}
