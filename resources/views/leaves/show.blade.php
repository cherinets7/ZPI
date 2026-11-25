@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $leave->user?->name }}</h1>
<p>{{ $leave->type === 'annual' ? __('ui.leave_annual') : __('ui.'.$leave->type) }} · {{ $leave->start_date->toDateString() }} – {{ $leave->end_date->toDateString() }} · {{ $leave->days }} {{ __('ui.days') }}</p>
<p>@include('partials.status', ['status' => $leave->status])</p>
<p>{{ $leave->reason }}</p>
@if($leave->review_note)<div class="alert alert-light border">{{ $leave->review_note }}</div>@endif
<div class="d-flex gap-2 mb-3">
    @can('update', $leave)<a class="btn btn-outline-secondary" href="#leave-edit">{{ __('ui.edit') }}</a>@endcan
    @can('delete', $leave)
        <form method="POST" action="{{ route('leaves.destroy', $leave) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('ui.delete') }}</button></form>
    @endcan
    @can('submit', $leave)
        <form method="POST" action="{{ route('leaves.submit', $leave) }}">@csrf<button class="btn btn-zpi">{{ __('ui.submit') }}</button></form>
    @endcan
</div>
@can('update', $leave)
    <form id="leave-edit" method="POST" action="{{ route('leaves.update', $leave) }}" class="card card-body mb-3" data-autosave>
        @csrf @method('PUT')
        <p class="small text-secondary" data-autosave-status data-failed="{{ __('ui.autosave_failed') }}" aria-live="polite"></p>
        <input type="hidden" name="type" value="{{ $leave->type }}">
        <div class="row">
            <div class="col-md-6 mb-2"><label class="form-label" for="leave-start">{{ __('ui.start_date') }}</label><input class="form-control" id="leave-start" type="date" name="start_date" value="{{ $leave->start_date->toDateString() }}"></div>
            <div class="col-md-6 mb-2"><label class="form-label" for="leave-end">{{ __('ui.end_date') }}</label><input class="form-control" id="leave-end" type="date" name="end_date" value="{{ $leave->end_date->toDateString() }}"></div>
        </div>
        <label class="form-label" for="leave-reason">{{ __('ui.reason') }}</label>
        <textarea class="form-control mb-2" id="leave-reason" name="reason" required>{{ $leave->reason }}</textarea>
        <button class="btn btn-outline-secondary">{{ __('ui.save') }}</button>
    </form>
@endcan
@can('decide', $leave)
    <form method="POST" action="{{ route('leaves.approve', $leave) }}" class="card card-body mb-2">
        @csrf
        <label class="form-label" for="leave-approve-note">{{ __('ui.review_note') }}</label>
        <textarea class="form-control mb-2" id="leave-approve-note" name="review_note"></textarea>
        <button class="btn btn-success">{{ __('ui.approve') }}</button>
    </form>
    <form method="POST" action="{{ route('leaves.reject', $leave) }}" class="card card-body">
        @csrf
        <label class="form-label" for="leave-reject-note">{{ __('ui.review_note') }}</label>
        <textarea class="form-control mb-2" id="leave-reject-note" name="review_note" required></textarea>
        <button class="btn btn-outline-danger">{{ __('ui.reject') }}</button>
    </form>
@endcan
@endsection
