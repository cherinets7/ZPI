@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $plan->exists ? __('ui.edit') : __('ui.create') }}</h1>
<form method="POST" action="{{ $plan->exists ? route('plans.update', $plan) : route('plans.store') }}" class="card card-body col-lg-8">
    @csrf
    @if($plan->exists) @method('PUT') @endif
    <div class="mb-3">
        <label class="form-label">{{ __('ui.kpis') }}</label>
        <select class="form-select" name="kpi_indicator_id" required>
            @foreach($indicators as $indicator)
                <option value="{{ $indicator->id }}" @selected(old('kpi_indicator_id', $plan->kpi_indicator_id) == $indicator->id)>{{ $indicator->title }} ({{ $indicator->unit }})</option>
            @endforeach
        </select>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('ui.type') }}</label>
            <select class="form-select" name="type">
                @foreach(config('workflow.plan_types') as $type)
                    <option value="{{ $type }}" @selected(old('type', $plan->type) === $type)>{{ __('ui.'.$type) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('ui.period') }}</label>
            <select class="form-select" name="period_type">
                @foreach(config('workflow.period_types') as $type)
                    <option value="{{ $type }}" @selected(old('period_type', $plan->period_type) === $type)>{{ __('ui.'.$type) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 mb-3"><label class="form-label">{{ __('ui.budget_year') }}</label><input class="form-control" type="number" name="eth_year" value="{{ old('eth_year', $plan->eth_year) }}" required></div>
        <div class="col-md-2 mb-3"><label class="form-label">{{ __('ui.period_no') }}</label><input class="form-control" type="number" name="eth_period" value="{{ old('eth_period', $plan->eth_period) }}" required></div>
    </div>
    <p class="small text-secondary">{{ __('ui.budget_help') }} {{ __('ui.monday_sunday') }}</p>
    <div class="mb-3"><label class="form-label">{{ __('ui.title') }}</label><input class="form-control" name="title" value="{{ old('title', $plan->title) }}" required></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">{{ __('ui.target') }}</label><input class="form-control" type="number" step="0.01" name="target_value" value="{{ old('target_value', $plan->target_value) }}" required></div>
        <div class="col-md-6 mb-3"><label class="form-label">{{ __('ui.achieved') }}</label><input class="form-control" type="number" step="0.01" name="achieved_value" value="{{ old('achieved_value', $plan->achieved_value) }}"></div>
    </div>
    <div class="mb-3"><label class="form-label">{{ __('ui.notes') }}</label><textarea class="form-control" name="notes" rows="3">{{ old('notes', $plan->notes) }}</textarea></div>
    <button class="btn btn-zpi" type="submit">{{ __('ui.save') }}</button>
</form>
@endsection
