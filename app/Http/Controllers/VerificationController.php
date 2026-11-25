<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Payroll;
use App\Models\PlanAndReport;
use App\Models\Setting;
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
        if ($payroll) {
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

        $issued = AuditLog::query()
            ->where('action', 'executive.issued')
            ->where('new_values->verification_hash', $hash)
            ->latest('id')
            ->first();
        abort_unless($issued, 404);

        $values = $issued->new_values ?? [];

        return view('verify.show', [
            'document' => [
                'kind' => __('ui.executive'),
                'title' => __('ui.budget_year').' '.($values['budget_year'] ?? ''),
                'person' => Setting::institutionName(),
                'department' => __('ui.cross_kpi'),
                'period' => __('ui.budget_year').' '.($values['budget_year'] ?? ''),
                'detail' => __('ui.payroll').': '.number_format((float) ($values['paid_total'] ?? 0), 2).' ETB',
                'status' => 'issued',
                'hash' => $hash,
            ],
        ]);
    }
}
