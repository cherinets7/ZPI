@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h1 class="h3">{{ __('ui.leave_field') }}</h1>
    @can('create', App\Models\LeaveRequest::class)
        <a class="btn btn-zpi" href="{{ route('leaves.create') }}">{{ __('ui.create') }}</a>
    @endcan
</div>
<div class="table-responsive card"><table class="table mb-0">
    <thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.type') }}</th><th>{{ __('ui.start_date') }}</th><th>{{ __('ui.end_date') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($leaves as $leave)
        <tr>
            <td>{{ $leave->user?->name }}</td>
            <td>{{ $leave->type === 'annual' ? __('ui.leave_annual') : __('ui.'.$leave->type) }}</td>
            <td>{{ $leave->start_date->toDateString() }}</td>
            <td>{{ $leave->end_date->toDateString() }}</td>
            <td>@include('partials.status', ['status' => $leave->status])</td>
            <td><a href="{{ route('leaves.show', $leave) }}">{{ __('ui.view') }}</a></td>
        </tr>
    @empty
        <tr><td colspan="6">{{ __('ui.no_records') }}</td></tr>
    @endforelse
    </tbody>
</table></div>
<div class="mt-3">{{ $leaves->links() }}</div>
@endsection
