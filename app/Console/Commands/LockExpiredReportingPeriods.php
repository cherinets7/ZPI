<?php

namespace App\Console\Commands;

use App\Models\PlanAndReport;
use App\Models\Setting;
use App\Support\EthiopianDateHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class LockExpiredReportingPeriods extends Command
{
    protected $signature = 'reporting:lock-expired';

    protected $description = 'Lock plans and reports whose reporting period has ended past the grace window.';

    public function handle(): int
    {
        $grace = (int) Setting::getValue('lock_grace_days', config('workflow.default_lock_grace_days'));
        $today = now()->startOfDay();
        $count = 0;

        PlanAndReport::query()
            ->whereNull('locked_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($grace, $today, &$count) {
                foreach ($rows as $row) {
                    $end = $row->period_end ? Carbon::parse($row->period_end)->startOfDay() : null;
                    if (! $end) {
                        try {
                            $end = EthiopianDateHelper::periodBounds(
                                $row->period_type,
                                (int) $row->eth_year,
                                (int) $row->eth_period
                            )['end']->copy()->startOfDay();
                        } catch (\Throwable) {
                            continue;
                        }
                    }

                    if ($end->copy()->addDays($grace)->lt($today)) {
                        $row->forceFill(['locked_at' => now()])->save();
                        $count++;
                    }
                }
            });

        $this->info("Locked {$count} reporting records.");

        return self::SUCCESS;
    }
}
