<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrDailyLog extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'work_date',
        'eth_year',
        'eth_month',
        'eth_day',
        'status',
        'hours',
        'note',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'hours' => 'decimal:2',
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

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
