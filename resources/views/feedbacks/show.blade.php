@extends('layouts.app')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h3">{{ __('ui.feedback') }}</h1>
        <div class="text-secondary">{{ $feedback->sender?->name }} · {{ $feedback->department?->name }} · {{ __('ui.'.$feedback->kind) }}</div>
    </div>
    <div class="d-flex gap-2">
        @can('update', $feedback)
            <a class="btn btn-outline-secondary" href="{{ route('feedbacks.edit', $feedback) }}">{{ __('ui.edit') }}</a>
        @endcan
        @can('delete', $feedback)
            <form method="POST" action="{{ route('feedbacks.destroy', $feedback) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('ui.delete') }}</button></form>
        @endcan
    </div>
</div>
@if($feedback->plan)
    <p><a href="{{ route('plans.show', $feedback->plan) }}">{{ $feedback->plan->title }}</a></p>
@endif
<p>{{ $feedback->comment }}</p>
@if($feedback->action_taken)
    <div class="alert alert-light border"><strong>{{ __('ui.action_taken') }}:</strong> {{ $feedback->action_taken }}</div>
@elseif((int) $feedback->receiver_id === (int) auth()->id())
    <form method="POST" action="{{ route('feedbacks.update', $feedback) }}" class="card card-body col-lg-8">
        @csrf @method('PUT')
        <label class="form-label" for="feedback-action-taken">{{ __('ui.action_taken') }}</label>
        <textarea class="form-control mb-2" id="feedback-action-taken" name="action_taken" required></textarea>
        <button class="btn btn-outline-secondary">{{ __('ui.save') }}</button>
    </form>
@endif
@endsection
