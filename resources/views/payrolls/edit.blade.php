@extends('layouts.app')
@section('content')
<h1 class="h3">{{ __('ui.edit') }}</h1>
<p>{{ $payroll->user?->name }} · {{ \App\Support\EthiopianDateHelper::monthName((int) $payroll->eth_month) }} {{ $payroll->eth_year }}</p>
<p class="text-secondary">{{ __('ui.bonus_recalculate') }}</p>
<form method="POST" action="{{ route('payrolls.update', $payroll) }}" class="card card-body col-lg-6">
    @csrf @method('PUT')
    <p>{{ __('ui.base_salary') }}: {{ number_format((float) $payroll->base_salary, 2) }}</p>
    <p>{{ __('ui.score') }}: {{ $payroll->performance_score }} · {{ __('ui.attendance_ratio') }}: {{ $payroll->attendance_ratio }}</p>
    <div class="mb-3">
        <label class="form-label" for="payroll-bonus">{{ __('ui.bonus') }}</label>
        <input class="form-control" id="payroll-bonus" type="number" step="0.01" name="bonus" value="{{ old('bonus', $bonus) }}" required>
    </div>
    <button class="btn btn-zpi">{{ __('ui.save') }}</button>
</form>
@endsection
