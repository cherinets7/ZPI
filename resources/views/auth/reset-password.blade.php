@extends('layouts.guest')
@section('content')
<h1 class="h4 mb-3">{{ __('ui.reset_password') }}</h1>
<form method="POST" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="mb-3">
        <label class="form-label" for="email">{{ __('ui.email') }}</label>
        <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">{{ __('ui.new_password') }}</label>
        <input class="form-control" id="password" type="password" name="password" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="password_confirmation">{{ __('ui.confirm_password') }}</label>
        <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" required>
    </div>
    <button class="btn btn-zpi w-100" type="submit">{{ __('ui.reset_password') }}</button>
</form>
@endsection
