@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h1 class="h3">{{ __('ui.departments') }}</h1>
    @can('create', App\Models\Department::class)
        <a class="btn btn-zpi" href="{{ route('departments.create') }}">{{ __('ui.create') }}</a>
    @endcan
</div>
<div class="table-responsive card"><table class="table mb-0">
    <thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.code') }}</th><th>{{ __('ui.head') }}</th><th>{{ __('ui.acting_head') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($departments as $department)
        <tr>
            <td>{{ $department->name }}</td>
            <td>{{ $department->code }}</td>
            <td>{{ $department->head?->name ?? '—' }}</td>
            <td>{{ $department->hasValidActingHead() ? $department->actingHead?->name : '—' }}</td>
            <td>@include('partials.status', ['status' => $department->is_active ? 'active' : 'inactive'])</td>
            <td><a href="{{ route('departments.show', $department) }}">{{ __('ui.view') }}</a></td>
        </tr>
    @endforeach
    </tbody>
</table></div>
<div class="mt-3">{{ $departments->links() }}</div>
@endsection
