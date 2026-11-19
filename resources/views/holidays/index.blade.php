@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h1 class="h3">{{ __('ui.holidays') }}</h1>
    @can('create', App\Models\Holiday::class)
        <a class="btn btn-zpi" href="{{ route('holidays.create') }}">{{ __('ui.create') }}</a>
    @endcan
</div>
<p class="small text-secondary">{{ __('ui.holiday_excluded') }}</p>
<div class="table-responsive card"><table class="table mb-0">
    <thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.period') }}</th><th>Gregorian</th><th></th></tr></thead>
    <tbody>
    @forelse($holidays as $holiday)
        <tr>
            <td>{{ app()->getLocale() === 'am' && $holiday->name_am ? $holiday->name_am : $holiday->name }}</td>
            <td>{{ \App\Support\EthiopianDateHelper::monthName((int) $holiday->eth_month) }} {{ $holiday->eth_day }}, {{ $holiday->eth_year }}</td>
            <td>{{ $holiday->gregorian_date->toDateString() }}</td>
            <td>
                @can('update', $holiday)
                    <a href="{{ route('holidays.edit', $holiday) }}">{{ __('ui.edit') }}</a>
                    <form class="d-inline" method="POST" action="{{ route('holidays.destroy', $holiday) }}">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger">{{ __('ui.delete') }}</button></form>
                @endcan
            </td>
        </tr>
    @empty
        <tr><td colspan="4">{{ __('ui.no_records') }}</td></tr>
    @endforelse
    </tbody>
</table></div>
<div class="mt-3">{{ $holidays->links() }}</div>
@endsection
