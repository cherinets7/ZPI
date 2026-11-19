@extends('layouts.app')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <h1 class="h3 mb-0">{{ __('ui.plans_reports') }}</h1>
    <div class="d-flex gap-2">
        @can('create', App\Models\PlanAndReport::class)
            <a class="btn btn-zpi btn-sm" href="{{ route('plans.create') }}">{{ __('ui.create') }}</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('plans.create', ['period_type' => 'daily']) }}">{{ __('ui.daily_progress') }}</a>
        @endcan
        @can('export', App\Models\PlanAndReport::class)
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('exports.plans') }}">{{ __('ui.export_excel') }}</a>
        @endcan
    </div>
</div>
<p class="text-secondary small">{{ __('ui.monday_sunday') }} {{ __('ui.budget_help') }}</p>
<form class="row g-2 mb-3" method="GET">
    <div class="col-md-3">
        <select class="form-select" name="status">
            <option value="">{{ __('ui.all') }}</option>
            @foreach(config('workflow.plan_statuses') as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('ui.'.$status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select" name="period_type">
            <option value="">{{ __('ui.all') }}</option>
            @foreach(config('workflow.period_types') as $type)
                <option value="{{ $type }}" @selected(request('period_type') === $type)>{{ __('ui.'.$type) }}</option>
            @endforeach
        </select>
    </div>
    @if(auth()->user()->seesAllDepartments())
        <div class="col-md-3">
            <select class="form-select" name="department_id">
                <option value="">{{ __('ui.departments') }}</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-md-2"><button class="btn btn-outline-secondary">{{ __('ui.filter') }}</button></div>
</form>
<div class="table-responsive card">
    <table class="table mb-0">
        <thead><tr><th>{{ __('ui.title') }}</th><th>{{ __('ui.department') }}</th><th>{{ __('ui.period') }}</th><th>{{ __('ui.score') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($plans as $plan)
            <tr>
                <td>{{ $plan->title }}<div class="small text-secondary">{{ $plan->user?->name }} · {{ __('ui.'.$plan->type) }}</div></td>
                <td>{{ $plan->department?->name }}</td>
                <td>{{ \App\Support\EthiopianDateHelper::periodLabel($plan->period_type, (int) $plan->eth_year, (int) $plan->eth_period) }}</td>
                <td>{{ $plan->performance_score ?? '—' }}</td>
                <td>@include('partials.status', ['status' => $plan->status]) @if($plan->locked_at)<span class="badge text-bg-dark">{{ __('ui.locked') }}</span>@endif</td>
                <td><a href="{{ route('plans.show', $plan) }}">{{ __('ui.view') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-secondary">{{ __('ui.no_records') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $plans->links() }}</div>
@endsection
