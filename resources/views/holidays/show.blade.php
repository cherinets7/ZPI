@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between gap-2">
    <div>
        <h1 class="h3">{{ app()->getLocale() === 'am' && $holiday->name_am ? $holiday->name_am : $holiday->name }}</h1>
        <p class="text-secondary">{{ \App\Support\EthiopianDateHelper::monthName((int) $holiday->eth_month) }} {{ $holiday->eth_day }}, {{ $holiday->eth_year }} · {{ $holiday->gregorian_date->toDateString() }}</p>
    </div>
    @can('update', $holiday)
        <a class="btn btn-outline-secondary" href="{{ route('holidays.edit', $holiday) }}">{{ __('ui.edit') }}</a>
    @endcan
</div>
<p>{{ $holiday->is_recurring ? __('ui.yes') : __('ui.no') }}</p>
@can('delete', $holiday)
    <form method="POST" action="{{ route('holidays.destroy', $holiday) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('ui.delete') }}</button></form>
@endcan
@endsection
