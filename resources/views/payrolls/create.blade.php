@extends('layouts.app')
@section('content')
<h1 class="h3">{{ __('ui.calculate') }}</h1>
<p>{{ __('ui.no_flat_deduction') }}</p>
<form class="row g-2 mb-3" method="GET">
    <div class="col-md-4">
        <select class="form-select" name="department_id" required>
            <option value="">{{ __('ui.department') }}</option>
            @foreach($departments as $item)
                <option value="{{ $item->id }}" @selected(optional($department)->id === $item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2"><input class="form-control" type="number" name="eth_year" value="{{ $ethYear }}"></div>
    <div class="col-md-3">
        <select class="form-select" name="eth_month">
            @for($month = 1; $month <= 13; $month++)
                <option value="{{ $month }}" @selected((int) $ethMonth === $month)>{{ \App\Support\EthiopianDateHelper::monthName($month) }}</option>
            @endfor
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-outline-secondary">{{ __('ui.filter') }}</button></div>
</form>
@if($department)
    <form method="POST" action="{{ route('payrolls.store') }}" class="card card-body">
        @csrf
        <input type="hidden" name="department_id" value="{{ $department->id }}">
        <input type="hidden" name="eth_year" value="{{ $ethYear }}">
        <input type="hidden" name="eth_month" value="{{ $ethMonth }}">
        <table class="table">
            <thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.base_salary') }}</th><th>{{ __('ui.bonus') }}</th></tr></thead>
            <tbody>
            @forelse($employees as $employee)
                <tr>
                    <td>{{ $employee->name }}</td>
                    <td>{{ number_format((float) $employee->base_salary, 2) }}</td>
                    <td><input class="form-control" type="number" step="0.01" name="bonus[{{ $employee->id }}]" value="0"></td>
                </tr>
            @empty
                <tr><td colspan="3">{{ __('ui.no_records') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <button class="btn btn-zpi" @disabled($employees->isEmpty())>{{ __('ui.calculate') }}</button>
    </form>
@endif
@endsection
