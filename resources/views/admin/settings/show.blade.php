@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between gap-2 mb-3">
    <h1 class="h3">{{ $setting->key }}</h1>
    <div class="d-flex gap-2">
        @can('update', $setting)
            <a class="btn btn-outline-secondary" href="{{ route('admin.settings.records.edit', $setting) }}">{{ __('ui.edit') }}</a>
        @endcan
        @can('delete', $setting)
            <form method="POST" action="{{ route('admin.settings.records.destroy', $setting) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('ui.delete') }}</button></form>
        @endcan
    </div>
</div>
<p class="card card-body">{{ $setting->value }}</p>
@if($setting->isProtected())
    <p class="small text-secondary">{{ __('ui.protected_setting') }}</p>
@endif
@endsection
