@extends('layouts.app')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h3">{{ __('ui.executive') }}</h1>
        <div class="text-secondary">{{ __('ui.budget_year') }} {{ $budgetYear }} · {{ __('ui.cross_kpi') }}</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('executive.excel') }}">{{ __('ui.export_excel') }}</a>
        <a class="btn btn-outline-secondary" href="{{ route('executive.pdf') }}">{{ __('ui.export_pdf') }}</a>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card card-body"><div class="text-secondary">{{ __('ui.payroll') }}</div><div class="fs-4">{{ number_format($paidTotal, 2) }} ETB</div><div class="small">{{ __('ui.payroll_readonly') }}</div></div></div>
    <div class="col-md-6"><div class="card card-body"><div class="text-secondary">{{ __('ui.totals') }}</div><div class="fs-4">{{ number_format($payrollTotal, 2) }} ETB</div></div></div>
</div>
<div class="card mb-4"><div class="card-body"><canvas data-chart='@json($chart)'></canvas></div></div>
<div class="table-responsive card mb-4"><table class="table mb-0">
    <thead><tr><th>{{ __('ui.department') }}</th><th>{{ __('ui.score') }}</th><th>{{ __('ui.payroll') }}</th></tr></thead>
    <tbody>
    @foreach($rows as $row)
        <tr><td>{{ $row['department'] }}</td><td>{{ $row['score'] }}</td><td>{{ number_format($row['payroll'], 2) }}</td></tr>
    @endforeach
    </tbody>
</table></div>
<h2 class="h5">{{ __('ui.directive') }}</h2>
<p class="text-secondary">{{ __('ui.directive_help') }}</p>
<form method="POST" action="{{ route('executive.directives.store') }}" class="card card-body col-lg-8">
    @csrf
    <div class="mb-3">
        <select class="form-select" name="department_id" required>
            @foreach($departments as $department)
                <option value="{{ $department->id }}">{{ $department->name }}</option>
            @endforeach
        </select>
    </div>
    <textarea class="form-control mb-3" name="comment" required rows="3"></textarea>
    <button class="btn btn-zpi">{{ __('ui.directive') }}</button>
</form>
@endsection
