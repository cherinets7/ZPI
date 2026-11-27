@extends('layouts.app')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h3">{{ $log->user?->name }}</h1>
        <div class="text-secondary">{{ $log->department?->name }} · {{ optional($log->work_date)->toDateString() }} · {{ \App\Support\EthiopianDateHelper::format($log->work_date) }}</div>
    </div>
    <div class="d-flex gap-2">
        @can('update', $log)
            <a class="btn btn-outline-secondary" href="{{ route('hr.attendance.edit', $log) }}">{{ __('ui.edit') }}</a>
        @endcan
        @can('delete', $log)
            <form method="POST" action="{{ route('hr.attendance.destroy', $log) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('ui.delete') }}</button></form>
        @endcan
    </div>
</div>
<p>@include('partials.status', ['status' => $log->status])</p>
<p>{{ __('ui.hours') }}: {{ $log->hours }}</p>
@if($log->note)<p>{{ $log->note }}</p>@endif
<p class="small text-secondary">{{ $log->recorder?->name }}</p>
@endsection
