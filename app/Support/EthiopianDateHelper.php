<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Ethiopian (Ge'ez) calendar conversion and budget-year rules.
 *
 * Budget year runs from Hamle 1 through Sene 30 and is numbered by the
 * Ethiopian year in which Sene falls. Reporting weeks are Monday–Sunday.
 * Working time is Monday–Friday.
 */
class EthiopianDateHelper
{
    public const MONTHS_EN = [
        1 => 'Meskerem',
        2 => 'Tikimt',
        3 => 'Hidar',
        4 => 'Tahsas',
        5 => 'Tir',
        6 => 'Yekatit',
        7 => 'Megabit',
        8 => 'Miazia',
        9 => 'Ginbot',
        10 => 'Sene',
        11 => 'Hamle',
        12 => 'Nehase',
        13 => 'Pagume',
    ];

    public const MONTHS_AM = [
        1 => 'መስከረም',
        2 => 'ጥቅምት',
        3 => 'ኅዳር',
        4 => 'ታኅሣሥ',
        5 => 'ጥር',
        6 => 'የካቲት',
        7 => 'መጋቢት',
        8 => 'ሚያዝያ',
        9 => 'ግንቦት',
        10 => 'ሰኔ',
        11 => 'ሐምሌ',
        12 => 'ነሐሴ',
        13 => 'ጳጉሜ',
    ];

    public static function daysInMonth(int $year, int $month): int
    {
        if ($month === 13) {
            return $year % 4 === 3 ? 6 : 5;
        }

        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Invalid Ethiopian month.');
        }

        return 30;
    }

    public static function assertDate(int $year, int $month, int $day): void
    {
        if ($year < 1 || $month < 1 || $month > 13) {
            throw new InvalidArgumentException('Invalid Ethiopian date.');
        }

        $max = self::daysInMonth($year, $month);
        if ($day < 1 || $day > $max) {
            throw new InvalidArgumentException('Invalid Ethiopian day.');
        }
    }

    public static function toJdn(int $year, int $month, int $day): int
    {
        self::assertDate($year, $month, $day);

        return (1723856 + 365)
            + (365 * ($year - 1))
            + intdiv($year, 4)
            + (30 * $month)
            + $day
            - 31;
    }

    /**
     * @return array{year: int, month: int, day: int}
     */
    public static function fromJdn(int $jdn): array
    {
        $r = ($jdn - 1723856) % 1461;
        $n = ($r % 365) + (365 * intdiv($r, 1460));

        $year = (4 * intdiv($jdn - 1723856, 1461))
            + intdiv($r, 365)
            - intdiv($r, 1460);
        $month = intdiv($n, 30) + 1;
        $day = ($n % 30) + 1;

        return ['year' => $year, 'month' => $month, 'day' => $day];
    }

    /**
     * @return array{year: int, month: int, day: int}
     */
    public static function fromCarbon(CarbonInterface $date): array
    {
        $jdn = gregoriantojd((int) $date->month, (int) $date->day, (int) $date->year);
        if ($jdn <= 0) {
            throw new InvalidArgumentException('Invalid Gregorian date.');
        }

        return self::fromJdn($jdn);
    }

    public static function toCarbon(int $year, int $month, int $day): Carbon
    {
        $jdn = self::toJdn($year, $month, $day);
        $parts = explode('/', jdtogregorian($jdn));

        return Carbon::create(
            (int) $parts[2],
            (int) $parts[0],
            (int) $parts[1],
            0,
            0,
            0,
            config('app.timezone', 'Africa/Addis_Ababa')
        );
    }

    public static function format(CarbonInterface $date, ?string $locale = null): string
    {
        $locale = $locale ?? (function_exists('app') ? app()->getLocale() : 'en');
        $eth = self::fromCarbon($date);
        $name = $locale === 'am'
            ? self::MONTHS_AM[$eth['month']]
            : self::MONTHS_EN[$eth['month']];

        if ($locale === 'am') {
            return $name.' '.$eth['day'].' ቀን '.$eth['year'].' ዓ.ም.';
        }

        return $name.' '.$eth['day'].', '.$eth['year'];
    }

    public static function monthName(int $month, ?string $locale = null): string
    {
        $locale = $locale ?? (function_exists('app') ? app()->getLocale() : 'en');

        return $locale === 'am' ? self::MONTHS_AM[$month] : self::MONTHS_EN[$month];
    }

    /**
     * Budget year is the Ethiopian year of Sene (the year in which the budget ends).
     */
    public static function budgetYearOf(CarbonInterface $date): int
    {
        $eth = self::fromCarbon($date);

        return $eth['month'] >= 11 ? $eth['year'] + 1 : $eth['year'];
    }

    public static function budgetYearStart(int $budgetYear): Carbon
    {
        return self::toCarbon($budgetYear - 1, 11, 1);
    }

    public static function budgetYearEnd(int $budgetYear): Carbon
    {
        return self::toCarbon($budgetYear, 10, 30);
    }

    /**
     * @return array{0: int, 1: int} [budgetYear, budgetMonthIndex 1=Hamle … 13=Sene]
     */
    public static function budgetPosition(int $calendarYear, int $calendarMonth): array
    {
        if ($calendarMonth >= 11) {
            return [$calendarYear + 1, $calendarMonth - 10];
        }

        return [$calendarYear, $calendarMonth + 3];
    }

    /**
     * @return array{year: int, month: int}
     */
    public static function calendarFromBudgetMonth(int $budgetYear, int $budgetMonthIndex): array
    {
        if ($budgetMonthIndex < 1 || $budgetMonthIndex > 13) {
            throw new InvalidArgumentException('Invalid budget month.');
        }

        if ($budgetMonthIndex <= 3) {
            return ['year' => $budgetYear - 1, 'month' => $budgetMonthIndex + 10];
        }

        return ['year' => $budgetYear, 'month' => $budgetMonthIndex - 3];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public static function calendarMonthBounds(int $calendarYear, int $calendarMonth): array
    {
        $start = self::toCarbon($calendarYear, $calendarMonth, 1);
        $end = self::toCarbon($calendarYear, $calendarMonth, self::daysInMonth($calendarYear, $calendarMonth));

        return ['start' => $start, 'end' => $end];
    }

    public static function mondayOf(CarbonInterface $date): Carbon
    {
        $day = Carbon::parse($date->toDateString(), config('app.timezone', 'Africa/Addis_Ababa'))->startOfDay();
        $iso = (int) $day->isoWeekday();

        return $day->subDays($iso - 1);
    }

    public static function isWorkingDay(CarbonInterface $date): bool
    {
        return in_array((int) $date->isoWeekday(), config('workflow.working_iso_days', [1, 2, 3, 4, 5]), true);
    }

    /**
     * @return array{budget_year: int, week: int, start: Carbon, end: Carbon}
     */
    public static function weekIdentity(CarbonInterface $date): array
    {
        $monday = self::mondayOf($date);
        $budgetYear = self::budgetYearOf($date);
        $firstMonday = self::mondayOf(self::budgetYearStart($budgetYear));
        $days = (int) round(($monday->copy()->startOfDay()->timestamp - $firstMonday->copy()->startOfDay()->timestamp) / 86400);
        $week = intdiv($days, 7) + 1;

        if ($week < 1) {
            throw new InvalidArgumentException('Date is outside the resolved budget week.');
        }

        return [
            'budget_year' => $budgetYear,
            'week' => $week,
            'start' => $monday,
            'end' => $monday->copy()->addDays(6),
        ];
    }

    public static function weekCount(int $budgetYear): int
    {
        $last = self::weekIdentity(self::budgetYearEnd($budgetYear));

        return $last['week'];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public static function periodBounds(string $periodType, int $budgetYear, int $ethPeriod): array
    {
        return match ($periodType) {
            'daily' => self::dailyBounds($budgetYear, $ethPeriod),
            'weekly' => self::weeklyBounds($budgetYear, $ethPeriod),
            'monthly' => self::monthlyBounds($budgetYear, $ethPeriod),
            'quarterly' => self::quarterlyBounds($budgetYear, $ethPeriod),
            'semi_annual' => self::semiAnnualBounds($budgetYear, $ethPeriod),
            'annual' => $ethPeriod === 1
                ? ['start' => self::budgetYearStart($budgetYear), 'end' => self::budgetYearEnd($budgetYear)]
                : throw new InvalidArgumentException('Annual period must be 1.'),
            default => throw new InvalidArgumentException('Unknown period type.'),
        };
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private static function dailyBounds(int $budgetYear, int $encoded): array
    {
        $month = intdiv($encoded, 100);
        $day = $encoded % 100;
        $calendar = $month >= 11
            ? ['year' => $budgetYear - 1, 'month' => $month]
            : ['year' => $budgetYear, 'month' => $month];
        $date = self::toCarbon($calendar['year'], $calendar['month'], $day);
        if (self::budgetYearOf($date) !== $budgetYear) {
            throw new InvalidArgumentException('Daily period is outside the budget year.');
        }

        return ['start' => $date->copy(), 'end' => $date->copy()];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private static function weeklyBounds(int $budgetYear, int $week): array
    {
        if ($week < 1 || $week > self::weekCount($budgetYear)) {
            throw new InvalidArgumentException('Invalid budget week.');
        }

        $start = self::mondayOf(self::budgetYearStart($budgetYear))->addDays(($week - 1) * 7);

        return ['start' => $start, 'end' => $start->copy()->addDays(6)];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private static function monthlyBounds(int $budgetYear, int $index): array
    {
        $calendar = self::calendarFromBudgetMonth($budgetYear, $index);

        return self::calendarMonthBounds($calendar['year'], $calendar['month']);
    }

    /**
     * Q1 Hamle–Pagume, Q2 Meskerem–Hidar, Q3 Tahsas–Yekatit, Q4 Megabit–Sene.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    private static function quarterlyBounds(int $budgetYear, int $quarter): array
    {
        $map = [
            1 => [1, 3],
            2 => [4, 6],
            3 => [7, 9],
            4 => [10, 13],
        ];
        if (! isset($map[$quarter])) {
            throw new InvalidArgumentException('Quarter must be 1 to 4.');
        }

        return [
            'start' => self::monthlyBounds($budgetYear, $map[$quarter][0])['start'],
            'end' => self::monthlyBounds($budgetYear, $map[$quarter][1])['end'],
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private static function semiAnnualBounds(int $budgetYear, int $half): array
    {
        if ($half === 1) {
            return [
                'start' => self::monthlyBounds($budgetYear, 1)['start'],
                'end' => self::monthlyBounds($budgetYear, 6)['end'],
            ];
        }
        if ($half === 2) {
            return [
                'start' => self::monthlyBounds($budgetYear, 7)['start'],
                'end' => self::monthlyBounds($budgetYear, 13)['end'],
            ];
        }

        throw new InvalidArgumentException('Six-month period must be 1 or 2.');
    }

    public static function encodeDaily(int $calendarMonth, int $day): int
    {
        return ($calendarMonth * 100) + $day;
    }

    public static function periodLabel(string $periodType, int $budgetYear, int $ethPeriod, ?string $locale = null): string
    {
        $locale = $locale ?? (function_exists('app') ? app()->getLocale() : 'en');

        return match ($periodType) {
            'daily' => self::format(self::dailyBounds($budgetYear, $ethPeriod)['start'], $locale),
            'weekly' => ($locale === 'am' ? 'ሳምንት ' : 'Week ').$ethPeriod.' / '.$budgetYear,
            'monthly' => self::monthName(self::calendarFromBudgetMonth($budgetYear, $ethPeriod)['month'], $locale).' '.$budgetYear,
            'quarterly' => ($locale === 'am' ? 'ሩብ ' : 'Q').$ethPeriod.' '.$budgetYear,
            'semi_annual' => ($locale === 'am' ? 'የስድስት ወር ' : 'H').$ethPeriod.' '.$budgetYear,
            'annual' => ($locale === 'am' ? 'የበጀት ዓመት ' : 'Budget year ').$budgetYear,
            default => $periodType,
        };
    }
}
