<?php

namespace Tests\Unit;

use App\Support\EthiopianDateHelper;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EthiopianDateHelperTest extends TestCase
{
    public function test_known_gregorian_conversions(): void
    {
        $this->assertSame(
            ['year' => 2016, 'month' => 1, 'day' => 1],
            EthiopianDateHelper::fromCarbon(Carbon::parse('2023-09-12'))
        );
        $this->assertSame('2023-09-11', EthiopianDateHelper::toCarbon(2015, 13, 6)->toDateString());
        $this->assertSame('2024-01-01', EthiopianDateHelper::toCarbon(2016, 4, 22)->toDateString());
        $this->assertSame('2024-09-11', EthiopianDateHelper::toCarbon(2017, 1, 1)->toDateString());
        $this->assertSame(6, EthiopianDateHelper::daysInMonth(2015, 13));
        $this->assertSame(5, EthiopianDateHelper::daysInMonth(2016, 13));
    }

    public function test_budget_year_runs_from_hamle_to_sene_and_weeks_are_monday_to_sunday(): void
    {
        $start = EthiopianDateHelper::budgetYearStart(2017);
        $end = EthiopianDateHelper::budgetYearEnd(2017);

        $this->assertSame(2017, EthiopianDateHelper::budgetYearOf($start));
        $this->assertSame(2017, EthiopianDateHelper::budgetYearOf($end));
        $this->assertSame(2016, EthiopianDateHelper::budgetYearOf($start->copy()->subDay()));

        $week = EthiopianDateHelper::periodBounds('weekly', 2017, 1);
        $this->assertSame(1, (int) $week['start']->isoWeekday());
        $this->assertSame(7, (int) $week['end']->isoWeekday());
        $this->assertTrue($week['start']->lte($start) && $week['end']->gte($start));
        $this->assertTrue(EthiopianDateHelper::isWorkingDay($week['start']));
        $this->assertFalse(EthiopianDateHelper::isWorkingDay($week['end']));
        $this->assertGreaterThan(50, EthiopianDateHelper::weekCount(2017));

        $quarter = EthiopianDateHelper::periodBounds('quarterly', 2017, 1);
        $this->assertTrue($quarter['start']->equalTo($start));
        $half = EthiopianDateHelper::periodBounds('semi_annual', 2017, 2);
        $this->assertTrue($half['end']->equalTo($end));
    }
}
