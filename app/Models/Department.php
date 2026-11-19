<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
        'code',
        'head_id',
        'acting_head_id',
        'acting_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'acting_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    public function actingHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acting_head_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(KpiIndicator::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(PlanAndReport::class);
    }

    public function hasValidActingHead(): bool
    {
        return $this->acting_head_id
            && $this->acting_until
            && $this->acting_until->toDateString() >= now()->toDateString();
    }
}
