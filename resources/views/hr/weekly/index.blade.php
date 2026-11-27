@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h1 class="h3">{{ __('ui.weekly_summaries') }}</h1>
    @can('prepare', App\Models\HrWeeklySummary::class)
        <a class="btn btn-zpi" href="{{ route('hr.weekly.create') }}">{{ __('ui.create') }}</a>
    @endcan
</div>
@can('prepare', App\Models\HrWeeklySummary::class)
    <form method="POST" action="{{ route('hr.weekly.generate') }}" class="card card-body mb-3">
        @csrf
        <label class="form-label">{{ __('ui.generate_week') }}</label>
        <div class="d-flex gap-2"><input class="form-control" type="date" name="date" value="{{ now()->toDateString() }}" required><button class="btn btn-zpi">{{ __('ui.generate') }}</button></div>
    </form>
@endcan
<div class="table-responsive card"><table class="table mb-0">
    <thead><tr><th>{{ __('ui.department') }}</th><th>{{ __('ui.period') }}</th><th>{{ __('ui.expected_hours') }}</th><th>{{ __('ui.worked_hours') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($summaries as $summary)
        <tr>
            <td>{{ $summary->department?->name }}</td>
            <td>{{ __('ui.weekly') }} {{ $summary->eth_period }} / {{ $summary->eth_year }}</td>
            <td>{{ $summary->expected_hours }}</td>
            <td>{{ $summary->worked_hours }}</td>
            <td>@include('partials.status', ['status' => $summary->status])</td>
            <td><a href="{{ route('hr.weekly.show', $summary) }}">{{ __('ui.view') }}</a></td>
        </tr>
    @empty
        @include('partials.empty', [
            'columns' => 6,
            'title' => __('ui.empty_weekly_title'),
            'body' => auth()->user()->can('prepare', App\Models\HrWeeklySummary::class) ? __('ui.empty_weekly_body') : __('ui.empty_weekly_wait'),
        ])
    @endforelse
    </tbody>
</table></div>
<div class="mt-3">{{ $summaries->links() }}</div>
@endsection
