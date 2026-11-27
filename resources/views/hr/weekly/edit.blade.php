@extends('layouts.app')
@section('content')
<h1 class="h3">{{ __('ui.edit') }}</h1>
<p class="text-secondary">{{ __('ui.weekly_note_only') }}</p>
<form method="POST" action="{{ route('hr.weekly.update', $summary) }}" class="card card-body col-lg-7">
    @csrf @method('PUT')
    <p>{{ __('ui.expected_hours') }}: {{ $summary->expected_hours }} · {{ __('ui.worked_hours') }}: {{ $summary->worked_hours }}</p>
    <label class="form-label" for="weekly-note">{{ __('ui.review_note') }}</label>
    <textarea class="form-control mb-3" id="weekly-note" name="review_note">{{ old('review_note', $summary->review_note) }}</textarea>
    <button class="btn btn-zpi">{{ __('ui.save') }}</button>
</form>
@endsection
