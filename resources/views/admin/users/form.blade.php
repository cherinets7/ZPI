@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $account->exists ? __('ui.edit') : __('ui.create') }}</h1>
<form method="POST" action="{{ $account->exists ? route('admin.users.update', $account) : route('admin.users.store') }}" class="card card-body col-lg-7">
    @csrf
    @if($account->exists) @method('PUT') @endif
    <div class="mb-3"><label class="form-label">{{ __('ui.name') }}</label><input class="form-control" name="name" value="{{ old('name', $account->name) }}" required></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.email') }}</label><input class="form-control" type="email" name="email" value="{{ old('email', $account->email) }}" required></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.password') }}</label><input class="form-control" type="password" name="password" {{ $account->exists ? '' : 'required' }}></div>
    <div class="mb-3">
        <label class="form-label">{{ __('ui.role') }}</label>
        <select class="form-select" name="role">
            @foreach(config('workflow.roles') as $role)
                <option value="{{ $role }}" @selected(old('role', $account->role) === $role)>{{ __('ui.role_'.$role) }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">{{ __('ui.department') }}</label>
        <select class="form-select" name="department_id">
            <option value="">{{ __('ui.none') }}</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected(old('department_id', $account->department_id) == $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3"><label class="form-label">{{ __('ui.base_salary') }}</label><input class="form-control" type="number" step="0.01" name="base_salary" value="{{ old('base_salary', $account->base_salary ?? 0) }}"></div>
    <div class="mb-3">
        <label class="form-label">{{ __('ui.status') }}</label>
        <select class="form-select" name="status">
            <option value="active" @selected(old('status', $account->status) === 'active')>{{ __('ui.active') }}</option>
            <option value="inactive" @selected(old('status', $account->status) === 'inactive')>{{ __('ui.inactive') }}</option>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">{{ __('ui.locale') }}</label>
        <select class="form-select" name="locale">
            <option value="en" @selected(old('locale', $account->locale) === 'en')>English</option>
            <option value="am" @selected(old('locale', $account->locale) === 'am')>አማርኛ</option>
        </select>
    </div>
    <button class="btn btn-zpi">{{ __('ui.save') }}</button>
</form>
@endsection
