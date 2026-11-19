<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: notosansethiopic, sans-serif; font-size: 12px; }
        h1 { color: #0f3d32; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #cfd8d4; padding: 6px; }
    </style>
</head>
<body>
    <h1>{{ $institution }}</h1>
    <div>{{ __('ui.executive') }} · {{ __('ui.budget_year') }} {{ $budgetYear }}</div>
    <p>{{ __('ui.payroll') }}: {{ number_format($paidTotal, 2) }} ETB</p>
    <table>
        <thead><tr><th>{{ __('ui.department') }}</th><th>{{ __('ui.score') }}</th><th>{{ __('ui.payroll') }}</th></tr></thead>
        <tbody>
        @foreach($rows as $row)
            <tr><td>{{ $row['department'] }}</td><td>{{ $row['score'] }}</td><td>{{ number_format($row['payroll'], 2) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
