@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between">
    <h1 class="h3">{{ $department->name }} <span class="text-secondary">{{ $department->code }}</span></h1>
    @can('update', $department)<a class="btn btn-outline-secondary" href="{{ route('departments.edit', $department) }}">{{ __('ui.edit') }}</a>@endcan
</div>
<p>{{ __('ui.head') }}: {{ $department->head?->name ?? '—' }}</p>
<p>{{ __('ui.acting_head') }}: {{ $department->hasValidActingHead() ? $department->actingHead?->name.' ('.$department->acting_until->toDateString().')' : '—' }}</p>
@can('delegate', $department)
    <form method="POST" action="{{ route('departments.acting', $department) }}" class="card card-body mb-3 col-lg-7">
        @csrf
        <h2 class="h6">{{ __('ui.delegate') }}</h2>
        <div class="mb-2">
            <select class="form-select" name="acting_head_id">
                <option value="">{{ __('ui.none') }}</option>
                @foreach($candidates as $candidate)
                    <option value="{{ $candidate->id }}" @selected($department->acting_head_id == $candidate->id)>{{ $candidate->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-2"><input class="form-control" type="date" name="acting_until" value="{{ optional($department->acting_until)->toDateString() }}"></div>
        <button class="btn btn-zpi">{{ __('ui.save') }}</button>
    </form>
@endcan
@can('delete', $department)
    <form method="POST" action="{{ route('departments.destroy', $department) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('ui.deactivate') }}</button></form>
@endcan
<h2 class="h5 mt-4">{{ __('ui.employees') }}</h2>
<ul>@foreach($department->users as $user)<li>{{ $user->name }} — {{ __('ui.role_'.$user->role) }}</li>@endforeach</ul>
@endsection
