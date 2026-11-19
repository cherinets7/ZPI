@extends('layouts.app')
@section('content')
<h1 class="h3">{{ __('ui.leave_field') }}</h1>
<form method="POST" action="{{ $leave->exists ? route('leaves.update', $leave) : route('leaves.store') }}" class="card card-body col-lg-7">
    @csrf
    @if($leave->exists) @method('PUT') @endif
    <div class="mb-3">
        <label class="form-label">{{ __('ui.type') }}</label>
        <select class="form-select" name="type">
            @foreach(config('workflow.leave_types') as $type)
                <option value="{{ $type }}" @selected(old('type', $leave->type) === $type)>{{ $type === 'annual' ? __('ui.leave_annual') : __('ui.'.$type) }}</option>
            @endforeach
        </select>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">{{ __('ui.start_date') }}</label><input class="form-control" type="date" name="start_date" value="{{ old('start_date', optional($leave->start_date)->toDateString()) }}" required></div>
        <div class="col-md-6 mb-3"><label class="form-label">{{ __('ui.end_date') }}</label><input class="form-control" type="date" name="end_date" value="{{ old('end_date', optional($leave->end_date)->toDateString()) }}" required></div>
    </div>
    <div class="mb-3"><label class="form-label">{{ __('ui.reason') }}</label><textarea class="form-control" name="reason" required>{{ old('reason', $leave->reason) }}</textarea></div>
    <button class="btn btn-zpi">{{ __('ui.save') }}</button>
</form>
@endsection
