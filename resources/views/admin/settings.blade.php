@extends('layouts.app')
@section('content')
<h1 class="h3">{{ __('ui.system_settings') }}</h1>
<p class="text-secondary">{{ __('ui.system_only') }}</p>
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="card card-body col-lg-8">
    @csrf
    @method('PUT')
    <div class="mb-3"><label class="form-label">{{ __('ui.institution_name') }}</label><input class="form-control" name="institution_name" value="{{ old('institution_name', $values['institution_name']) }}" required></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.institution_name_am') }}</label><input class="form-control" name="institution_name_am" value="{{ old('institution_name_am', $values['institution_name_am']) }}" required></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.logo') }}</label>@if($values['logo_path'])<div class="mb-2"><img src="{{ asset('storage/'.$values['logo_path']) }}" alt="" style="max-height:64px"></div>@endif<input class="form-control" type="file" name="logo" accept="image/*"></div>
    <div class="row">
        <div class="col-md-4 mb-3"><label class="form-label">{{ __('ui.budget_year') }}</label><input class="form-control" type="number" name="budget_year" value="{{ old('budget_year', $values['budget_year']) }}" required></div>
        <div class="col-md-4 mb-3"><label class="form-label">{{ __('ui.lock_grace_days') }}</label><input class="form-control" type="number" name="lock_grace_days" value="{{ old('lock_grace_days', $values['lock_grace_days']) }}" required></div>
        <div class="col-md-4 mb-3"><label class="form-label">{{ __('ui.hours_per_day') }}</label><input class="form-control" type="number" step="0.5" name="hours_per_day" value="{{ old('hours_per_day', $values['hours_per_day']) }}" required></div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">{{ __('ui.attendance_weight') }}</label><input class="form-control" type="number" step="0.01" name="attendance_weight" value="{{ old('attendance_weight', $values['attendance_weight']) }}" required></div>
        <div class="col-md-6 mb-3"><label class="form-label">{{ __('ui.performance_weight') }}</label><input class="form-control" type="number" step="0.01" name="performance_weight" value="{{ old('performance_weight', $values['performance_weight']) }}" required></div>
    </div>
    <p class="small text-secondary">{{ __('ui.no_flat_deduction') }} {{ __('ui.budget_help') }}</p>
    <button class="btn btn-zpi">{{ __('ui.save') }}</button>
</form>
<div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <h2 class="h5 mb-0">{{ __('ui.setting_records') }}</h2>
    <a class="btn btn-outline-secondary" href="{{ route('admin.settings.records.create') }}">{{ __('ui.create') }}</a>
</div>
<div class="table-responsive card"><table class="table mb-0">
    <thead><tr><th>{{ __('ui.setting_key') }}</th><th>{{ __('ui.setting_value') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($records as $setting)
        <tr>
            <td>{{ $setting->key }}</td>
            <td>{{ \Illuminate\Support\Str::limit((string) $setting->value, 80) }}</td>
            <td><a href="{{ route('admin.settings.records.show', $setting) }}">{{ __('ui.view') }}</a></td>
        </tr>
    @empty
        @include('partials.empty', ['columns' => 3, 'title' => __('ui.empty_settings_title'), 'body' => __('ui.empty_settings_body')])
    @endforelse
    </tbody>
</table></div>
@endsection
