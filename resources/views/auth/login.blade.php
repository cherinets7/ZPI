@extends('layouts.guest')
@section('title', __('ui.login'))
@section('content')
<h1 class="h4 mb-3">{{ __('ui.login') }}</h1>
<p class="text-secondary">{{ __('ui.sign_in_continue') }}</p>
<form method="POST" action="{{ route('login') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="email">{{ __('ui.email') }}</label>
        <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">{{ __('ui.password') }}</label>
        <input class="form-control" id="password" name="password" type="password" required>
    </div>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
        <label class="form-check-label" for="remember">{{ __('ui.remember') }}</label>
    </div>
    <button class="btn btn-zpi w-100" type="submit">{{ __('ui.login') }}</button>
</form>
<p class="mt-3 mb-0"><a href="{{ route('password.request') }}">{{ __('ui.forgot_password') }}</a></p>
@endsection
