@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $indicator->exists ? __('ui.edit') : __('ui.create') }}</h1>
<form method="POST" action="{{ $indicator->exists ? route('kpis.update', $indicator) : route('kpis.store') }}" class="card card-body col-lg-7">
    @csrf
    @if($indicator->exists) @method('PUT') @endif
    <div class="mb-3">
        <label class="form-label">{{ __('ui.department') }}</label>
        <select class="form-select" name="department_id">
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected(old('department_id', $indicator->department_id) == $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3"><label class="form-label">{{ __('ui.title') }}</label><input class="form-control" name="title" value="{{ old('title', $indicator->title) }}" required></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.unit') }}</label><input class="form-control" name="unit" value="{{ old('unit', $indicator->unit) }}" required></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.weight') }}</label><input class="form-control" type="number" step="0.01" name="weight" value="{{ old('weight', $indicator->weight) }}" required></div>
    <div class="mb-3">
        <select class="form-select" name="is_active">
            <option value="1" @selected(old('is_active', $indicator->is_active ? '1' : '0') == '1')>{{ __('ui.active') }}</option>
            <option value="0" @selected(old('is_active', $indicator->is_active ? '1' : '0') == '0')>{{ __('ui.inactive') }}</option>
        </select>
    </div>
    <button class="btn btn-zpi">{{ __('ui.save') }}</button>
</form>
@endsection
