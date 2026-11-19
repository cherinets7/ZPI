@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 mb-1">{{ __('ui.greeting') }}, {{ auth()->user()->name }}</h1>
        <div class="text-secondary">{{ $todayLabel }} · {{ __('ui.budget_year') }} {{ $budgetYear }}</div>
    </div>
</div>
<div class="row g-3 mb-4">
    @foreach($counts as $key => $value)
        <div class="col-6 col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="text-secondary small">{{ __('ui.'.$key) }}</div>
                    <div class="fs-3 fw-semibold">{{ $value }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@if($payrollTotal !== null)
    <div class="alert alert-light border">{{ __('ui.payroll') }}: <strong>{{ number_format($payrollTotal, 2) }} ETB</strong>. {{ __('ui.payroll_readonly') }}</div>
@endif
@if($attendance)
    <div class="row g-3 mb-4">
        @foreach($attendance as $key => $value)
            <div class="col-md-4">
                <div class="card"><div class="card-body"><div class="text-secondary">{{ __('ui.'.$key) }}</div><div class="fs-4">{{ $value }}</div></div></div>
            </div>
        @endforeach
    </div>
@endif
<div class="row g-4">
    @foreach($charts as $chart)
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6">{{ $chart['title'] }}</h2>
                    <canvas data-chart='@json($chart['config'])'></canvas>
                </div>
            </div>
        </div>
    @endforeach
    @if($leaderboard->isNotEmpty())
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6">{{ __('ui.leaderboard') }}</h2>
                    <ol class="mb-0">
                        @foreach($leaderboard as $row)
                            <li class="d-flex justify-content-between py-1"><span>{{ $row['name'] }}</span><strong>{{ $row['score'] }}</strong></li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
