@extends('layouts.app')
@section('content')
<h1 class="h3">{{ __('ui.personal_settings') }}</h1>
<p class="text-secondary">{{ __('ui.settings_personal_help') }}</p>
<form method="POST" action="{{ route('settings.update') }}" class="card card-body col-lg-7">
    @csrf
    @method('PUT')
    <div class="mb-3"><label class="form-label">{{ __('ui.name') }}</label><input class="form-control" name="name" value="{{ old('name', $user->name) }}" required></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.email') }}</label><input class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
    <div class="mb-3">
        <label class="form-label">{{ __('ui.locale') }}</label>
        <select class="form-select" name="locale">
            <option value="en" @selected(old('locale', $user->locale) === 'en')>{{ __('ui.english') }}</option>
            <option value="am" @selected(old('locale', $user->locale) === 'am')>{{ __('ui.amharic') }}</option>
        </select>
    </div>
    <div class="mb-3"><label class="form-label">{{ __('ui.current_password') }}</label><input class="form-control" type="password" name="current_password"></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.new_password') }}</label><input class="form-control" type="password" name="password"></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.confirm_password') }}</label><input class="form-control" type="password" name="password_confirmation"></div>
    <button class="btn btn-zpi" type="submit">{{ __('ui.save') }}</button>
</form>
@endsection
