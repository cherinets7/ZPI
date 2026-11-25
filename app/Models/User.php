<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'department_id',
        'role',
        'name',
        'email',
        'password',
        'base_salary',
        'status',
        'locale',
        'theme',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'base_salary' => 'decimal:2',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(PlanAndReport::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function dailyLogs(): HasMany
    {
        return $this->hasMany(HrDailyLog::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(EmployeeTransfer::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isDepartmentRole(): bool
    {
        return in_array($this->role, config('workflow.department_roles', []), true);
    }

    public function isExecutive(): bool
    {
        return in_array($this->role, config('workflow.executive_roles', []), true);
    }

    public function seesAllDepartments(): bool
    {
        return $this->hasRole('plan_dept', 'zone_chief', 'zone_deputy', 'super_admin', 'finance_dept');
    }

    public function managesDepartment(Department|int|null $department): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $department = $department instanceof Department
            ? $department
            : Department::query()->find($department);

        if (! $department || ! $department->is_active) {
            return false;
        }

        if ((int) $this->department_id !== (int) $department->id) {
            return false;
        }

        if ((int) $department->head_id === (int) $this->id) {
            return true;
        }

        return (int) $department->acting_head_id === (int) $this->id
            && $department->acting_until instanceof Carbon
            && $department->acting_until->toDateString() >= now()->toDateString();
    }

    public function isActingHead(): bool
    {
        return Department::query()
            ->where('acting_head_id', $this->id)
            ->where('is_active', true)
            ->whereDate('acting_until', '>=', now()->toDateString())
            ->exists();
    }
}
