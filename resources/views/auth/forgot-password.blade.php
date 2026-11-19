@extends('layouts.guest')
@section('content')
<h1 class="h4 mb-3">{{ __('ui.forgot_password') }}</h1>
<form method="POST" action="{{ route('password.email') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="email">{{ __('ui.email') }}</label>
        <input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" required>
    </div>
    <button class="btn btn-zpi w-100" type="submit">{{ __('ui.send_reset') }}</button>
</form>
<p class="mt-3 mb-0"><a href="{{ route('login') }}">{{ __('ui.login') }}</a></p>
@endsection
