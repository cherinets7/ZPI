@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $summary->department?->name }}</h1>
<p>{{ __('ui.weekly') }} {{ $summary->eth_period }} / {{ $summary->eth_year }} · {{ $summary->period_start->toDateString() }} – {{ $summary->period_end->toDateString() }}</p>
<p>@include('partials.status', ['status' => $summary->status])</p>
<div class="row g-3 mb-3">
    @foreach(['expected_hours' => $summary->expected_hours, 'worked_hours' => $summary->worked_hours, 'absent_days' => $summary->absent_days, 'leave_days' => $summary->leave_days, 'holiday_days' => $summary->holiday_days] as $label => $value)
        <div class="col-md-4 col-lg-2"><div class="card card-body"><div class="small text-secondary">{{ __('ui.'.$label) }}</div><div class="fs-5">{{ $value }}</div></div></div>
    @endforeach
</div>
@if($summary->review_note)<div class="alert alert-light border">{{ $summary->review_note }}</div>@endif
<div class="d-flex flex-wrap gap-2">
    @can('verify', $summary)
        <form method="POST" action="{{ route('hr.weekly.verify', $summary) }}">@csrf<button class="btn btn-zpi">{{ __('ui.verify') }}</button></form>
    @endcan
    @can('forward', $summary)
        <form method="POST" action="{{ route('hr.weekly.forward', $summary) }}">@csrf<button class="btn btn-zpi">{{ __('ui.forward') }}</button></form>
    @endcan
</div>
@can('decide', $summary)
    <form method="POST" action="{{ route('hr.weekly.approve', $summary) }}" class="card card-body mt-3">
        @csrf
        <textarea class="form-control mb-2" name="review_note" placeholder="{{ __('ui.review_note') }}"></textarea>
        <button class="btn btn-success">{{ __('ui.approve') }}</button>
    </form>
    <form method="POST" action="{{ route('hr.weekly.reject', $summary) }}" class="card card-body mt-2">
        @csrf
        <textarea class="form-control mb-2" name="review_note" required></textarea>
        <button class="btn btn-outline-danger">{{ __('ui.reject') }}</button>
    </form>
@endcan
@endsection
