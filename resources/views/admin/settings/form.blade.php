@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $setting->exists ? __('ui.edit') : __('ui.create') }}</h1>
@unless($setting->exists)
    <p class="text-secondary">{{ __('ui.protected_setting') }}</p>
@endunless
<form method="POST" action="{{ $setting->exists ? route('admin.settings.records.update', $setting) : route('admin.settings.records.store') }}" class="card card-body col-lg-7">
    @csrf
    @if($setting->exists) @method('PUT') @endif
    @if($setting->exists)
        <p><strong>{{ $setting->key }}</strong></p>
    @else
        <div class="mb-3">
            <label class="form-label" for="setting-key">{{ __('ui.setting_key') }}</label>
            <input class="form-control" id="setting-key" name="key" value="{{ old('key') }}" required pattern="[a-z][a-z0-9_]*">
        </div>
    @endif
    <div class="mb-3">
        <label class="form-label" for="setting-value">{{ __('ui.setting_value') }}</label>
        <textarea class="form-control" id="setting-value" name="value" required>{{ old('value', $setting->value) }}</textarea>
    </div>
    <button class="btn btn-zpi">{{ __('ui.save') }}</button>
</form>
@endsection
