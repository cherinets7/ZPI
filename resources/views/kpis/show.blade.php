@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between gap-2">
    <div>
        <h1 class="h3">{{ $indicator->title }}</h1>
        <p class="text-secondary">{{ $indicator->department?->name }} · {{ $indicator->unit }} · {{ __('ui.weight') }} {{ $indicator->weight }}</p>
    </div>
    @can('update', $indicator)
        <a class="btn btn-outline-secondary" href="{{ route('kpis.edit', $indicator) }}">{{ __('ui.edit') }}</a>
    @endcan
</div>
<p>@include('partials.status', ['status' => $indicator->is_active ? 'active' : 'inactive'])</p>
@can('delete', $indicator)
    <form method="POST" action="{{ route('kpis.destroy', $indicator) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('ui.delete') }}</button></form>
@endcan
@endsection
