@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h1 class="h3">{{ __('ui.kpis') }}</h1>
    @can('create', App\Models\KpiIndicator::class)
        <a class="btn btn-zpi" href="{{ route('kpis.create') }}">{{ __('ui.create') }}</a>
    @endcan
</div>
@can('create', App\Models\KpiIndicator::class)
    <form method="POST" action="{{ route('kpis.import') }}" enctype="multipart/form-data" class="card card-body mb-3">
        @csrf
        <p class="small mb-2">{{ __('ui.import_kpi_help') }}</p>
        <div class="d-flex gap-2"><input class="form-control" type="file" name="file" required><button class="btn btn-outline-secondary">{{ __('ui.import') }}</button></div>
    </form>
@endcan
<form class="row g-2 mb-3" method="GET">
    @if(auth()->user()->seesAllDepartments() || auth()->user()->role === 'super_admin')
        <div class="col-md-4">
            <select class="form-select" name="department_id">
                <option value="">{{ __('ui.all') }}</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-outline-secondary">{{ __('ui.filter') }}</button></div>
    @endif
</form>
<div class="table-responsive card"><table class="table mb-0">
    <thead><tr><th>{{ __('ui.title') }}</th><th>{{ __('ui.department') }}</th><th>{{ __('ui.unit') }}</th><th>{{ __('ui.weight') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($indicators as $indicator)
        <tr>
            <td>{{ $indicator->title }}</td>
            <td>{{ $indicator->department?->name }}</td>
            <td>{{ $indicator->unit }}</td>
            <td>{{ $indicator->weight }}</td>
            <td>@include('partials.status', ['status' => $indicator->is_active ? 'active' : 'inactive'])</td>
            <td>
                <a href="{{ route('kpis.show', $indicator) }}">{{ __('ui.view') }}</a>
                @can('update', $indicator)
                    <a href="{{ route('kpis.edit', $indicator) }}">{{ __('ui.edit') }}</a>
                    <form class="d-inline" method="POST" action="{{ route('kpis.destroy', $indicator) }}">@csrf @method('DELETE')<button class="btn btn-link btn-sm">{{ __('ui.delete') }}</button></form>
                @endcan
            </td>
        </tr>
    @empty
        @include('partials.empty', [
            'columns' => 6,
            'title' => request()->filled('department_id') ? __('ui.empty_filtered_title') : __('ui.empty_kpis_title'),
            'body' => request()->filled('department_id') ? __('ui.empty_filtered_body') : (auth()->user()->can('create', App\Models\KpiIndicator::class) ? __('ui.empty_kpis_body') : __('ui.empty_kpis_wait')),
            'action' => (! request()->filled('department_id') && auth()->user()->can('create', App\Models\KpiIndicator::class)) ? ['url' => route('kpis.create'), 'label' => __('ui.create')] : null,
        ])
    @endforelse
    </tbody>
</table></div>
<div class="mt-3">{{ $indicators->links() }}</div>
@endsection
