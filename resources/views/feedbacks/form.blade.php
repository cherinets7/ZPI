@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $feedback->exists ? __('ui.edit') : __('ui.create') }}</h1>
<form method="POST" action="{{ $feedback->exists ? route('feedbacks.update', $feedback) : route('feedbacks.store') }}" class="card card-body col-lg-8">
    @csrf
    @if($feedback->exists) @method('PUT') @endif
    @if(! $feedback->exists && $plans->isEmpty())
        <p class="mb-0">{{ __('ui.empty_feedback_body') }}</p>
    @elseif(! $feedback->exists)
        <div class="mb-3">
            <label class="form-label" for="feedback-plan">{{ __('ui.plans_reports') }}</label>
            <select class="form-select" id="feedback-plan" name="plan_and_report_id" required>
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" @selected(old('plan_and_report_id') == $plan->id)>{{ $plan->title }}</option>
                @endforeach
            </select>
        </div>
        @if(auth()->user()->isExecutive())
            <input type="hidden" name="kind" value="directive">
        @endif
    @endif
    @if($feedback->exists || $plans->isNotEmpty())
        @if($feedback->exists && (int) $feedback->receiver_id === (int) auth()->id() && (int) $feedback->sender_id !== (int) auth()->id())
            <label class="form-label" for="feedback-action">{{ __('ui.action_taken') }}</label>
            <textarea class="form-control mb-3" id="feedback-action" name="action_taken" required>{{ old('action_taken', $feedback->action_taken) }}</textarea>
        @else
            <label class="form-label" for="feedback-comment">{{ __('ui.comment') }}</label>
            <textarea class="form-control mb-3" id="feedback-comment" name="comment" required>{{ old('comment', $feedback->comment) }}</textarea>
        @endif
        <button class="btn btn-zpi">{{ __('ui.save') }}</button>
    @endif
</form>
@endsection
