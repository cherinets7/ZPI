@extends('layouts.guest')
@section('title', __('ui.two_factor'))
@section('content')
<h1 class="h4 mb-3">{{ __('ui.two_factor') }}</h1>
<p class="text-secondary">{{ __('ui.two_factor_challenge') }}</p>
<form method="POST" action="{{ route('two-factor.store') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="code">{{ __('ui.two_factor_code') }}</label>
        <input class="form-control" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus>
    </div>
    <button class="btn btn-zpi w-100" type="submit">{{ __('ui.login') }}</button>
</form>
@endsection
