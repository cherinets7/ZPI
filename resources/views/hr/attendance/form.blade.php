@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $log->exists ? __('ui.edit') : __('ui.create') }}</h1>
<p class="text-secondary">{{ __('ui.attendance_entry') }}</p>
<form method="POST" action="{{ $log->exists ? route('hr.attendance.update', $log) : route('hr.attendance.entries.store') }}" class="card card-body col-lg-7">
    @csrf
    @if($log->exists) @method('PUT') @endif
    <div class="mb-3">
        <label class="form-label" for="attendance-employee">{{ __('ui.name') }}</label>
        @if($log->exists)
            <input class="form-control" id="attendance-employee" value="{{ $log->user?->name }}" disabled>
        @else
            <select class="form-select" id="attendance-employee" name="user_id" required>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(old('user_id') == $employee->id)>{{ $employee->name }}</option>
                @endforeach
            </select>
        @endif
    </div>
    <div class="mb-3">
        <label class="form-label" for="attendance-date">{{ __('ui.work_date') }}</label>
        <input class="form-control" id="attendance-date" type="date" name="work_date" value="{{ old('work_date', optional($log->work_date)->toDateString()) }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="attendance-status">{{ __('ui.status') }}</label>
        <select class="form-select" id="attendance-status" name="status">
            @foreach(['present', 'absent', 'field_work'] as $status)
                <option value="{{ $status }}" @selected(old('status', $log->status) === $status)>{{ __('ui.'.$status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label" for="attendance-hours">{{ __('ui.hours') }}</label>
        <input class="form-control" id="attendance-hours" type="number" step="0.5" min="0" max="24" name="hours" value="{{ old('hours', $log->hours ?? 8) }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="attendance-note">{{ __('ui.notes') }}</label>
        <textarea class="form-control" id="attendance-note" name="note">{{ old('note', $log->note) }}</textarea>
    </div>
    <button class="btn btn-zpi">{{ __('ui.save') }}</button>
</form>
@endsection
