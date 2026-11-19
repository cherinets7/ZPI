<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();

        if (! $row || $row->value === null || $row->value === '') {
            return $default;
        }

        return $row->value;
    }

    public static function putValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function institutionName(): string
    {
        try {
            if (! Schema::hasTable('settings')) {
                return 'ZPI';
            }

            $key = app()->getLocale() === 'am' ? 'institution_name_am' : 'institution_name';

            return (string) (static::getValue($key) ?: 'ZPI');
        } catch (\Throwable) {
            return 'ZPI';
        }
    }

    public static function budgetYear(): int
    {
        $configured = static::getValue('budget_year');
        if ($configured) {
            return (int) $configured;
        }

        return \App\Support\EthiopianDateHelper::budgetYearOf(now());
    }
}
