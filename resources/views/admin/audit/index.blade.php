@extends('layouts.app')
@section('content')
<h1 class="h3">{{ __('ui.audit_logs') }}</h1>
<div class="table-responsive card"><table class="table mb-0">
    <thead><tr><th>{{ __('ui.today') }}</th><th>{{ __('ui.name') }}</th><th>{{ __('ui.actions') }}</th><th>{{ __('ui.department') }}</th></tr></thead>
    <tbody>
    @forelse($logs as $log)
        <tr>
            <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
            <td>{{ $log->user?->name ?? '—' }}</td>
            <td>{{ $log->action }}</td>
            <td>{{ $log->department?->name ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="4">{{ __('ui.no_records') }}</td></tr>
    @endforelse
    </tbody>
</table></div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
