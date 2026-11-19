<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Holiday;
use App\Models\KpiIndicator;
use App\Models\PlanAndReport;
use App\Models\Setting;
use App\Models\User;
use App\Support\EthiopianDateHelper;
use Illuminate\Database\Seeder;

class SystemSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Password@123';

    public function run(): void
    {
        Setting::putValue('institution_name', 'Zonal Performance Office');
        Setting::putValue('institution_name_am', 'የዞን አፈጻጸም ጽህፈት ቤት');
        Setting::putValue('lock_grace_days', '7');
        Setting::putValue('attendance_weight', '0.70');
        Setting::putValue('performance_weight', '0.30');
        Setting::putValue('hours_per_day', '8');

        $budgetYear = EthiopianDateHelper::budgetYearOf(now());
        Setting::putValue('budget_year', (string) $budgetYear);

        $offices = [
            'FIN' => 'Finance Office',
            'PLN' => 'Planning Office',
            'HLT' => 'Health Office',
            'EDU' => 'Education Office',
            'AGR' => 'Agriculture Office',
        ];

        $departments = [];
        foreach ($offices as $code => $name) {
            $departments[$code] = Department::query()->create([
                'name' => $name,
                'code' => $code,
                'is_active' => true,
            ]);
        }

        $this->user('System Admin', 'admin@zpi.gov.et', 'super_admin', null, 0);
        $this->user('Zone Chief', 'chief@zpi.gov.et', 'zone_chief', null, 0);
        $this->user('Zone Deputy', 'deputy@zpi.gov.et', 'zone_deputy', null, 0);
        $this->user('Finance Officer', 'finance@zpi.gov.et', 'finance_dept', $departments['FIN']->id, 0);
        $this->user('Planning Officer', 'plan@zpi.gov.et', 'plan_dept', $departments['PLN']->id, 0);

        $scores = ['HLT' => 92, 'EDU' => 78, 'AGR' => 85];
        $salaries = ['HLT' => 12000, 'EDU' => 10000, 'AGR' => 9000];

        foreach (['HLT' => 'health', 'EDU' => 'education', 'AGR' => 'agriculture'] as $code => $slug) {
            $department = $departments[$code];
            $head = $this->user(ucfirst($slug).' Head', "head.{$slug}@zpi.gov.et", 'dept_head', $department->id, 15000);
            $this->user(ucfirst($slug).' HR', "hr.{$slug}@zpi.gov.et", 'hr_specialist', $department->id, 11000);
            $this->user(ucfirst($slug).' Planner', "planning.{$slug}@zpi.gov.et", 'dev_plan_specialist', $department->id, 11000);
            $employee = $this->user(ucfirst($slug).' Employee', "employee.{$slug}@zpi.gov.et", 'employee', $department->id, $salaries[$code]);
            $department->update(['head_id' => $head->id]);

            $indicators = [
                KpiIndicator::query()->create([
                    'department_id' => $department->id,
                    'title' => $department->name.' service coverage',
                    'unit' => 'percent',
                    'weight' => 60,
                    'is_active' => true,
                ]),
                KpiIndicator::query()->create([
                    'department_id' => $department->id,
                    'title' => $department->name.' cases closed',
                    'unit' => 'number',
                    'weight' => 40,
                    'is_active' => true,
                ]),
            ];

            $this->seedMonthlyScore($employee, $department->id, $indicators, $scores[$code]);
        }

        $this->seedHolidays();
    }

    private function user(string $name, string $email, string $role, ?int $departmentId, float $salary): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => self::DEMO_PASSWORD,
            'role' => $role,
            'department_id' => $departmentId,
            'base_salary' => $salary,
            'status' => 'active',
            'locale' => 'en',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @param  list<KpiIndicator>  $indicators
     */
    private function seedMonthlyScore(User $employee, int $departmentId, array $indicators, float $score): void
    {
        $eth = EthiopianDateHelper::fromCarbon(now());
        $month = $eth['month'] - 1;
        $year = $eth['year'];
        if ($month < 1) {
            $month = 13;
            $year--;
        }

        [$budgetYear, $index] = EthiopianDateHelper::budgetPosition($year, $month);
        $bounds = EthiopianDateHelper::calendarMonthBounds($year, $month);

        foreach ($indicators as $indicator) {
            PlanAndReport::query()->create([
                'user_id' => $employee->id,
                'department_id' => $departmentId,
                'kpi_indicator_id' => $indicator->id,
                'type' => 'report',
                'period_type' => 'monthly',
                'eth_year' => $budgetYear,
                'eth_period' => $index,
                'title' => $indicator->title,
                'target_value' => 100,
                'achieved_value' => $score,
                'performance_score' => $score,
                'status' => 'approved',
                'is_rollup' => false,
                'period_start' => $bounds['start']->toDateString(),
                'period_end' => $bounds['end']->toDateString(),
                'approved_at' => now(),
            ]);
        }
    }

    private function seedHolidays(): void
    {
        $eth = EthiopianDateHelper::fromCarbon(now());
        $fixed = [
            [1, 1, 'Ethiopian New Year', 'እንቁጣጣሽ'],
            [1, 17, 'Meskel', 'መስቀል'],
            [4, 29, 'Genna', 'ገና'],
            [5, 11, 'Timkat', 'ጥምቀት'],
            [6, 23, 'Victory of Adwa', 'የዓድዋ ድል'],
            [8, 27, 'Patriots Victory Day', 'የአርበኞች ቀን'],
            [9, 20, 'Derg Downfall Day', 'ደርግ የወደቀበት ቀን'],
        ];

        foreach (range($eth['year'] - 1, $eth['year'] + 1) as $year) {
            foreach ($fixed as [$month, $day, $name, $nameAm]) {
                $date = EthiopianDateHelper::toCarbon($year, $month, $day);
                Holiday::query()->firstOrCreate(
                    ['gregorian_date' => $date->toDateString(), 'name' => $name],
                    [
                        'name_am' => $nameAm,
                        'eth_year' => $year,
                        'eth_month' => $month,
                        'eth_day' => $day,
                        'is_recurring' => true,
                    ]
                );
            }
        }
    }
}
