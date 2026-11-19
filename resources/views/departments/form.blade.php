@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $department->exists ? __('ui.edit') : __('ui.create') }}</h1>
<form method="POST" action="{{ $department->exists ? route('departments.update', $department) : route('departments.store') }}" class="card card-body col-lg-7">
    @csrf
    @if($department->exists) @method('PUT') @endif
    <div class="mb-3"><label class="form-label">{{ __('ui.name') }}</label><input class="form-control" name="name" value="{{ old('name', $department->name) }}" required></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.code') }}</label><input class="form-control" name="code" value="{{ old('code', $department->code) }}" required></div>
    <div class="mb-3">
        <label class="form-label">{{ __('ui.head') }}</label>
        <select class="form-select" name="head_id">
            <option value="">{{ __('ui.none') }}</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected(old('head_id', $department->head_id) == $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">{{ __('ui.status') }}</label>
        <select class="form-select" name="is_active">
            <option value="1" @selected(old('is_active', $department->is_active ? '1' : '0') == '1')>{{ __('ui.active') }}</option>
            <option value="0" @selected(old('is_active', $department->is_active ? '1' : '0') == '0')>{{ __('ui.inactive') }}</option>
        </select>
    </div>
    <button class="btn btn-zpi">{{ __('ui.save') }}</button>
</form>
@endsection
