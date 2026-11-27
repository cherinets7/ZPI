<?php

namespace App\Models;

use App\Support\EthiopianDateHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    /**
     * Keys read by payroll, the reporting calendar, and the institution header.
     * They are edited on the system form and cannot be deleted.
     */
    public const PROTECTED_KEYS = [
        'institution_name',
        'institution_name_am',
        'budget_year',
        'lock_grace_days',
        'attendance_weight',
        'performance_weight',
        'hours_per_day',
        'logo_path',
    ];

    protected $fillable = [
        'key',
        'value',
    ];

    public function isProtected(): bool
    {
        return in_array($this->key, self::PROTECTED_KEYS, true);
    }

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

        return EthiopianDateHelper::budgetYearOf(now());
    }
}
