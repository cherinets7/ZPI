@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $payroll->user?->name }}</h1>
<p>{{ $payroll->department?->name }} · {{ \App\Support\EthiopianDateHelper::monthName((int) $payroll->eth_month) }} {{ $payroll->eth_year }}</p>
<p>@include('partials.status', ['status' => $payroll->status])</p>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card card-body"><div class="small">{{ __('ui.base_salary') }}</div><div class="fs-5">{{ number_format((float) $payroll->base_salary, 2) }}</div></div></div>
    <div class="col-md-3"><div class="card card-body"><div class="small">{{ __('ui.score') }}</div><div class="fs-5">{{ $payroll->performance_score }}</div></div></div>
    <div class="col-md-3"><div class="card card-body"><div class="small">{{ __('ui.attendance_ratio') }}</div><div class="fs-5">{{ $payroll->attendance_ratio }}</div></div></div>
    <div class="col-md-3"><div class="card card-body"><div class="small">{{ __('ui.adjustment') }}</div><div class="fs-5">{{ number_format((float) $payroll->bonus_or_adjustment, 2) }}</div></div></div>
</div>
<div class="alert alert-success">{{ __('ui.net_pay') }}: <strong>{{ number_format((float) $payroll->net_pay, 2) }} ETB</strong></div>
<p class="small text-secondary">{{ __('ui.no_flat_deduction') }}</p>
@if($payroll->paid_at)
    <p>{{ __('ui.paid_at') }}: {{ $payroll->paid_at->format('Y-m-d H:i') }} · {{ $payroll->payer?->name }}</p>
@endif
<div class="d-flex gap-2">
    @can('update', $payroll)
        <a class="btn btn-outline-secondary" href="{{ route('payrolls.edit', $payroll) }}">{{ __('ui.edit') }}</a>
    @endcan
    @can('delete', $payroll)
        <form method="POST" action="{{ route('payrolls.destroy', $payroll) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('ui.delete') }}</button></form>
    @endcan
    @can('pay', $payroll)
        <form method="POST" action="{{ route('payrolls.pay', $payroll) }}">@csrf<button class="btn btn-zpi">{{ __('ui.pay') }}</button></form>
    @endcan
    @if($payroll->status === 'paid')
        <a class="btn btn-outline-secondary" href="{{ route('payrolls.pdf', $payroll) }}">{{ __('ui.receipt') }}</a>
    @endif
</div>
@endsection
