<?php

namespace App\Models;

use App\Support\EthiopianDateHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class HrWeeklySummary extends Model
{
    protected $fillable = [
        'department_id',
        'eth_year',
        'eth_period',
        'period_start',
        'period_end',
        'expected_hours',
        'worked_hours',
        'absent_days',
        'leave_days',
        'holiday_days',
        'status',
        'prepared_by',
        'verified_by',
        'approved_by',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'expected_hours' => 'decimal:2',
            'worked_hours' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function closesAttendance(int $departmentId, Carbon $date): bool
    {
        $identity = EthiopianDateHelper::weekIdentity($date);
        $summary = static::query()
            ->where('department_id', $departmentId)
            ->where('eth_year', $identity['budget_year'])
            ->where('eth_period', $identity['week'])
            ->first();

        return $summary !== null && in_array($summary->status, ['verified', 'forwarded', 'approved'], true);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('zone_chief', 'zone_deputy', 'super_admin', 'finance_dept', 'plan_dept')) {
            return $query;
        }

        return $query->where('department_id', $user->department_id);
    }
}
