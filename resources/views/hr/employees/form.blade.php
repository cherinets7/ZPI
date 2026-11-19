@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $employee->exists ? __('ui.edit') : __('ui.register_employee') }}</h1>
<form method="POST" action="{{ $employee->exists ? route('hr.employees.update', $employee) : route('hr.employees.store') }}" class="card card-body col-lg-7">
    @csrf
    @if($employee->exists) @method('PUT') @endif
    <div class="mb-3"><label class="form-label">{{ __('ui.name') }}</label><input class="form-control" name="name" value="{{ old('name', $employee->name) }}" required></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.email') }}</label><input class="form-control" type="email" name="email" value="{{ old('email', $employee->email) }}" required></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.password') }}</label><input class="form-control" type="password" name="password" {{ $employee->exists ? '' : 'required' }}></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.base_salary') }}</label><input class="form-control" type="number" step="0.01" min="0" name="base_salary" value="{{ old('base_salary', $employee->base_salary ?? 0) }}" required></div>
    <div class="mb-3">
        <label class="form-label">{{ __('ui.status') }}</label>
        <select class="form-select" name="status">
            <option value="active" @selected(old('status', $employee->status) === 'active')>{{ __('ui.active') }}</option>
            <option value="inactive" @selected(old('status', $employee->status) === 'inactive')>{{ __('ui.inactive') }}</option>
        </select>
    </div>
    <button class="btn btn-zpi">{{ __('ui.save') }}</button>
</form>
@endsection
