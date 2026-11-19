<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PlanAndReport;
use App\Support\EthiopianDateHelper;

class VerificationController extends Controller
{
    public function show(string $hash)
    {
        $plan = PlanAndReport::query()->with(['user', 'department'])->where('verification_hash', $hash)->first();
        if ($plan) {
            return view('verify.show', [
                'document' => [
                    'kind' => __('ui.official_report'),
                    'title' => $plan->title,
                    'person' => $plan->user?->name,
                    'department' => $plan->department?->name,
                    'period' => EthiopianDateHelper::periodLabel($plan->period_type, (int) $plan->eth_year, (int) $plan->eth_period),
                    'detail' => __('ui.score').': '.$plan->performance_score,
                    'status' => $plan->status,
                    'hash' => $plan->verification_hash,
                ],
            ]);
        }

        $payroll = Payroll::query()->with(['user', 'department'])->where('verification_hash', $hash)->first();
        abort_unless($payroll, 404);

        return view('verify.show', [
            'document' => [
                'kind' => __('ui.payslip'),
                'title' => EthiopianDateHelper::monthName((int) $payroll->eth_month).' '.$payroll->eth_year,
                'person' => $payroll->user?->name,
                'department' => $payroll->department?->name,
                'period' => EthiopianDateHelper::monthName((int) $payroll->eth_month).' '.$payroll->eth_year,
                'detail' => __('ui.net_pay').': '.number_format((float) $payroll->net_pay, 2).' ETB',
                'status' => $payroll->status,
                'hash' => $payroll->verification_hash,
            ],
        ]);
    }
}
