<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = [
        'name',
        'name_am',
        'eth_year',
        'eth_month',
        'eth_day',
        'gregorian_date',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'gregorian_date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }
}
