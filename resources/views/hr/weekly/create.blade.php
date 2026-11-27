@extends('layouts.app')
@section('content')
<h1 class="h3">{{ __('ui.create') }}</h1>
<p class="text-secondary">{{ __('ui.weekly_from_attendance') }}</p>
<form method="POST" action="{{ route('hr.weekly.generate') }}" class="card card-body col-lg-6">
    @csrf
    <label class="form-label" for="weekly-date">{{ __('ui.generate_week') }}</label>
    <input class="form-control mb-3" id="weekly-date" type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required>
    <button class="btn btn-zpi">{{ __('ui.generate') }}</button>
</form>
@endsection
