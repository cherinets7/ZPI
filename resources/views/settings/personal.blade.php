@extends('layouts.app')
@section('content')
<h1 class="h3">{{ __('ui.personal_settings') }}</h1>
<p class="text-secondary">{{ __('ui.settings_personal_help') }}</p>
<form method="POST" action="{{ route('settings.update') }}" class="card card-body col-lg-7">
    @csrf
    @method('PUT')
    <div class="mb-3"><label class="form-label" for="name">{{ __('ui.name') }}</label><input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required></div>
    <div class="mb-3"><label class="form-label" for="email">{{ __('ui.email') }}</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
    <div class="mb-3">
        <label class="form-label" for="locale">{{ __('ui.locale') }}</label>
        <select class="form-select" id="locale" name="locale">
            <option value="en" @selected(old('locale', $user->locale) === 'en')>{{ __('ui.english') }}</option>
            <option value="am" @selected(old('locale', $user->locale) === 'am')>{{ __('ui.amharic') }}</option>
        </select>
    </div>
    <div class="mb-3"><label class="form-label" for="current_password">{{ __('ui.current_password') }}</label><input class="form-control" id="current_password" type="password" name="current_password" autocomplete="current-password"></div>
    <div class="mb-3"><label class="form-label" for="password">{{ __('ui.new_password') }}</label><input class="form-control" id="password" type="password" name="password" autocomplete="new-password"></div>
    <div class="mb-3"><label class="form-label" for="password_confirmation">{{ __('ui.confirm_password') }}</label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"></div>
    <button class="btn btn-zpi" type="submit">{{ __('ui.save') }}</button>
</form>
<section class="card card-body col-lg-7 mt-3">
    <h2 class="h5">{{ __('ui.two_factor') }}</h2>
    <p class="text-secondary">{{ __('ui.two_factor_help') }}</p>
    @if(session('recovery_codes'))
        <div class="alert alert-warning">
            <p class="mb-2">{{ __('ui.two_factor_recovery_help') }}</p>
            <ul class="mb-0">
                @foreach(session('recovery_codes') as $code)
                    <li><code>{{ $code }}</code></li>
                @endforeach
            </ul>
        </div>
    @endif
    @if($user->two_factor_confirmed_at)
        <p class="mb-2">{{ __('ui.two_factor_on') }}</p>
        <form method="POST" action="{{ route('settings.two-factor.destroy') }}">
            @csrf
            @method('DELETE')
            <label class="form-label" for="disable_password">{{ __('ui.current_password') }}</label>
            <input class="form-control mb-2" id="disable_password" type="password" name="current_password" autocomplete="current-password" required>
            <button class="btn btn-outline-danger" type="submit">{{ __('ui.two_factor_disable') }}</button>
        </form>
    @else
        @php($setupSecret = session('two_factor.setup_secret'))
        @if($setupSecret)
            <img class="mb-3" src="{{ \App\Http\Controllers\TwoFactorController::qrDataUri($user->email, $setupSecret) }}" width="160" height="160" alt="{{ __('ui.two_factor') }}">
            <p class="small">{{ __('ui.two_factor_manual') }} <code>{{ $setupSecret }}</code></p>
            <form method="POST" action="{{ route('settings.two-factor.confirm') }}">
                @csrf
                <label class="form-label" for="two-factor-code">{{ __('ui.two_factor_code') }}</label>
                <input class="form-control mb-2" id="two-factor-code" name="code" inputmode="numeric" autocomplete="one-time-code" required>
                <button class="btn btn-zpi" type="submit">{{ __('ui.two_factor_confirm') }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('settings.two-factor.start') }}">
                @csrf
                <button class="btn btn-outline-secondary" type="submit">{{ __('ui.two_factor_enable') }}</button>
            </form>
        @endif
    @endif
</section>
@endsection
