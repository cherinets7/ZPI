@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h1 class="h3">{{ __('ui.employees') }}</h1>
    <a class="btn btn-zpi" href="{{ route('hr.employees.create') }}">{{ __('ui.register_employee') }}</a>
</div>
<form method="POST" action="{{ route('hr.employees.import') }}" enctype="multipart/form-data" class="card card-body mb-3">
    @csrf
    <label class="form-label">{{ __('ui.import') }}</label>
    <p class="small text-secondary">{{ __('ui.import_employees_help') }}</p>
    <div class="d-flex gap-2"><input class="form-control" type="file" name="file" required accept=".xlsx,.xls,.csv"><button class="btn btn-outline-secondary">{{ __('ui.import') }}</button></div>
</form>
<div class="table-responsive card"><table class="table mb-0">
    <thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.email') }}</th><th>{{ __('ui.base_salary') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($employees as $employee)
        <tr>
            <td>{{ $employee->name }}</td>
            <td>{{ $employee->email }}</td>
            <td>{{ number_format((float) $employee->base_salary, 2) }}</td>
            <td>@include('partials.status', ['status' => $employee->status])</td>
            <td>
                <a href="{{ route('hr.employees.edit', $employee) }}">{{ __('ui.edit') }}</a>
                <a class="ms-2" href="{{ route('hr.employees.transfer', $employee) }}">{{ __('ui.transfer') }}</a>
            </td>
        </tr>
    @empty
        @include('partials.empty', [
            'columns' => 5,
            'title' => __('ui.empty_employees_title'),
            'body' => __('ui.empty_employees_body'),
            'action' => ['url' => route('hr.employees.create'), 'label' => __('ui.register_employee')],
        ])
    @endforelse
    </tbody>
</table></div>
<div class="mt-3">{{ $employees->links() }}</div>
@endsection
