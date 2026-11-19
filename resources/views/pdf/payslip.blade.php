<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: notosansethiopic, sans-serif; font-size: 12px; color: #1c2421; }
        h1 { font-size: 18px; color: #0f3d32; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        td, th { border: 1px solid #cfd8d4; padding: 6px; text-align: left; }
        .muted { color: #5c6b66; }
    </style>
</head>
<body>
    <h1>{{ $institution }}</h1>
    <div>{{ __('ui.payslip') }}</div>
    <table>
        <tr><th>{{ __('ui.name') }}</th><td>{{ $payroll->user?->name }}</td></tr>
        <tr><th>{{ __('ui.department') }}</th><td>{{ $payroll->department?->name }}</td></tr>
        <tr><th>{{ __('ui.period') }}</th><td>{{ \App\Support\EthiopianDateHelper::monthName((int) $payroll->eth_month) }} {{ $payroll->eth_year }}</td></tr>
        <tr><th>{{ __('ui.base_salary') }}</th><td>{{ number_format((float) $payroll->base_salary, 2) }} ETB</td></tr>
        <tr><th>{{ __('ui.score') }}</th><td>{{ $payroll->performance_score }}</td></tr>
        <tr><th>{{ __('ui.attendance_ratio') }}</th><td>{{ $payroll->attendance_ratio }}</td></tr>
        <tr><th>{{ __('ui.adjustment') }}</th><td>{{ number_format((float) $payroll->bonus_or_adjustment, 2) }} ETB</td></tr>
        <tr><th>{{ __('ui.net_pay') }}</th><td>{{ number_format((float) $payroll->net_pay, 2) }} ETB</td></tr>
        <tr><th>{{ __('ui.paid_by') }}</th><td>{{ $payroll->payer?->name }}</td></tr>
    </table>
    <p class="muted">{{ __('ui.no_flat_deduction') }}</p>
    @if($qr)
        <p><img src="{{ $qr }}" width="110" height="110" alt="QR"></p>
        <div class="muted">{{ __('ui.qr_caption') }} {{ $verifyUrl }}</div>
    @endif
    <p class="muted">{{ __('ui.hash') }}: {{ $payroll->verification_hash }}</p>
</body>
</html>
