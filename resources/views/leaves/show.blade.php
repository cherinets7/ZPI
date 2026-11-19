@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $leave->user?->name }}</h1>
<p>{{ $leave->type === 'annual' ? __('ui.leave_annual') : __('ui.'.$leave->type) }} · {{ $leave->start_date->toDateString() }} – {{ $leave->end_date->toDateString() }} · {{ $leave->days }} {{ __('ui.days') }}</p>
<p>@include('partials.status', ['status' => $leave->status])</p>
<p>{{ $leave->reason }}</p>
@if($leave->review_note)<div class="alert alert-light border">{{ $leave->review_note }}</div>@endif
<div class="d-flex gap-2 mb-3">
    @can('update', $leave)<a class="btn btn-outline-secondary" href="{{ route('leaves.create') }}">{{ __('ui.edit') }}</a>@endcan
    @can('delete', $leave)
        <form method="POST" action="{{ route('leaves.destroy', $leave) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('ui.delete') }}</button></form>
    @endcan
    @can('submit', $leave)
        <form method="POST" action="{{ route('leaves.submit', $leave) }}">@csrf<button class="btn btn-zpi">{{ __('ui.submit') }}</button></form>
    @endcan
</div>
@can('update', $leave)
    <form method="POST" action="{{ route('leaves.update', $leave) }}" class="card card-body mb-3">
        @csrf @method('PUT')
        <input type="hidden" name="type" value="{{ $leave->type }}">
        <div class="row">
            <div class="col-md-6 mb-2"><input class="form-control" type="date" name="start_date" value="{{ $leave->start_date->toDateString() }}"></div>
            <div class="col-md-6 mb-2"><input class="form-control" type="date" name="end_date" value="{{ $leave->end_date->toDateString() }}"></div>
        </div>
        <textarea class="form-control mb-2" name="reason" required>{{ $leave->reason }}</textarea>
        <button class="btn btn-outline-secondary">{{ __('ui.save') }}</button>
    </form>
@endcan
@can('decide', $leave)
    <form method="POST" action="{{ route('leaves.approve', $leave) }}" class="card card-body mb-2">
        @csrf
        <textarea class="form-control mb-2" name="review_note" placeholder="{{ __('ui.review_note') }}"></textarea>
        <button class="btn btn-success">{{ __('ui.approve') }}</button>
    </form>
    <form method="POST" action="{{ route('leaves.reject', $leave) }}" class="card card-body">
        @csrf
        <textarea class="form-control mb-2" name="review_note" required></textarea>
        <button class="btn btn-outline-danger">{{ __('ui.reject') }}</button>
    </form>
@endcan
@endsection
