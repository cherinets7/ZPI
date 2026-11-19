<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTransfer extends Model
{
    protected $fillable = [
        'user_id',
        'from_department_id',
        'to_department_id',
        'transferred_by',
        'effective_on',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
