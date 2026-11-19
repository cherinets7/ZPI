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
    <div class="muted">{{ __('ui.official_report') }}</div>
    <h2>{{ $plan->title }}</h2>
    <table>
        <tr><th>{{ __('ui.name') }}</th><td>{{ $plan->user?->name }}</td></tr>
        <tr><th>{{ __('ui.department') }}</th><td>{{ $plan->department?->name }}</td></tr>
        <tr><th>{{ __('ui.kpis') }}</th><td>{{ $plan->kpiIndicator?->title }} ({{ $plan->kpiIndicator?->unit }})</td></tr>
        <tr><th>{{ __('ui.period') }}</th><td>{{ \App\Support\EthiopianDateHelper::periodLabel($plan->period_type, (int) $plan->eth_year, (int) $plan->eth_period) }}</td></tr>
        <tr><th>{{ __('ui.target') }}</th><td>{{ $plan->target_value }}</td></tr>
        <tr><th>{{ __('ui.achieved') }}</th><td>{{ $plan->achieved_value }}</td></tr>
        <tr><th>{{ __('ui.score') }}</th><td>{{ $plan->performance_score }}</td></tr>
        <tr><th>{{ __('ui.status') }}</th><td>{{ __('ui.'.$plan->status) }}</td></tr>
    </table>
    @if($qr)
        <p style="margin-top: 16px;"><img src="{{ $qr }}" width="110" height="110" alt="QR"></p>
        <div class="muted">{{ __('ui.qr_caption') }}</div>
        <div class="muted">{{ $verifyUrl }}</div>
    @endif
    <p class="muted">{{ __('ui.hash') }}: {{ $plan->verification_hash }}</p>
</body>
</html>
