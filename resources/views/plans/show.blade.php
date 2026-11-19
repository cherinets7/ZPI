@extends('layouts.app')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h3">{{ $plan->title }}</h1>
        <div class="text-secondary">{{ $plan->department?->name }} · {{ $plan->user?->name }} · {{ \App\Support\EthiopianDateHelper::periodLabel($plan->period_type, (int) $plan->eth_year, (int) $plan->eth_period) }}</div>
    </div>
    <div>@include('partials.status', ['status' => $plan->status])</div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card card-body"><div class="text-secondary">{{ __('ui.target') }}</div><div class="fs-4">{{ $plan->target_value }}</div></div></div>
    <div class="col-md-3"><div class="card card-body"><div class="text-secondary">{{ __('ui.achieved') }}</div><div class="fs-4">{{ $plan->achieved_value ?? '—' }}</div></div></div>
    <div class="col-md-3"><div class="card card-body"><div class="text-secondary">{{ __('ui.score') }}</div><div class="fs-4">{{ $plan->performance_score ?? '—' }}</div></div></div>
    <div class="col-md-3"><div class="card card-body"><div class="text-secondary">{{ __('ui.kpis') }}</div><div>{{ $plan->kpiIndicator?->title }}</div></div></div>
</div>
@if($plan->notes)<p>{{ $plan->notes }}</p>@endif
@if($plan->locked_at)<div class="alert alert-warning">{{ __('ui.locked') }}. {{ __('ui.unlock_help') }}</div>@endif
<div class="d-flex flex-wrap gap-2 mb-4">
    @can('update', $plan)<a class="btn btn-outline-secondary" href="{{ route('plans.edit', $plan) }}">{{ __('ui.edit') }}</a>@endcan
    @can('delete', $plan)
        <form method="POST" action="{{ route('plans.destroy', $plan) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('ui.delete') }}</button></form>
    @endcan
    @can('submit', $plan)
        <form method="POST" action="{{ route('plans.submit', $plan) }}">@csrf<button class="btn btn-zpi">{{ __('ui.submit') }}</button></form>
    @endcan
    @can('resubmit', $plan)
        <form method="POST" action="{{ route('plans.resubmit', $plan) }}">@csrf<button class="btn btn-zpi">{{ __('ui.resubmit') }}</button></form>
    @endcan
    @can('unlock', $plan)
        <form method="POST" action="{{ route('plans.unlock', $plan) }}">@csrf<button class="btn btn-outline-dark">{{ __('ui.unlock') }}</button></form>
    @endcan
    @if($plan->status === 'approved')
        <a class="btn btn-outline-secondary" href="{{ route('plans.pdf', $plan) }}">{{ __('ui.pdf') }}</a>
    @endif
</div>
@can('screen', $plan)
    <form method="POST" action="{{ route('plans.screen', $plan) }}" class="card card-body mb-3">
        @csrf
        <label class="form-label">{{ __('ui.screen') }}</label>
        <textarea class="form-control mb-2" name="comment" rows="2"></textarea>
        <button class="btn btn-zpi">{{ __('ui.forward') }}</button>
    </form>
@endcan
@can('approve', $plan)
    <form method="POST" action="{{ route('plans.approve', $plan) }}" class="card card-body mb-3">
        @csrf
        <label class="form-label">{{ __('ui.approve') }}</label>
        <textarea class="form-control mb-2" name="comment" required></textarea>
        <button class="btn btn-success">{{ __('ui.approve') }}</button>
    </form>
@endcan
@can('reject', $plan)
    <form method="POST" action="{{ route('plans.reject', $plan) }}" class="card card-body mb-3">
        @csrf
        <label class="form-label">{{ __('ui.reject') }}</label>
        <textarea class="form-control mb-2" name="comment" required></textarea>
        <button class="btn btn-outline-danger">{{ __('ui.reject') }}</button>
    </form>
@endcan
<h2 class="h5">{{ __('ui.feedback') }}</h2>
@can('feedback', $plan)
    <form method="POST" action="{{ route('plans.feedback.store', $plan) }}" class="card card-body mb-3">
        @csrf
        @if(auth()->user()->isExecutive())
            <input type="hidden" name="kind" value="directive">
        @endif
        <textarea class="form-control mb-2" name="comment" required placeholder="{{ __('ui.comment') }}"></textarea>
        <button class="btn btn-outline-secondary">{{ auth()->user()->isExecutive() ? __('ui.directive') : __('ui.feedback') }}</button>
    </form>
@endcan
@forelse($plan->feedbacks as $feedback)
    <div class="border rounded p-3 mb-2">
        <div class="small text-secondary">{{ $feedback->sender?->name }} · {{ __('ui.'.$feedback->kind) }} · {{ $feedback->created_at->format('Y-m-d H:i') }}</div>
        <p class="mb-1">{{ $feedback->comment }}</p>
        @if($feedback->action_taken)
            <div><strong>{{ __('ui.action_taken') }}:</strong> {{ $feedback->action_taken }}</div>
        @elseif((int) $feedback->receiver_id === (int) auth()->id())
            <form method="POST" action="{{ route('feedbacks.update', $feedback) }}" class="mt-2">
                @csrf @method('PUT')
                <textarea class="form-control mb-2" name="action_taken" required></textarea>
                <button class="btn btn-sm btn-outline-secondary">{{ __('ui.action_taken') }}</button>
            </form>
        @endif
    </div>
@empty
    <p class="text-secondary">{{ __('ui.no_records') }}</p>
@endforelse
@endsection
