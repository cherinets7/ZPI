@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h3">{{ $account->name }}</h1>
        <p class="text-secondary">{{ $account->email }}</p>
    </div>
    @can('update', $account)
        <a class="btn btn-outline-secondary" href="{{ route('admin.users.edit', $account) }}">{{ __('ui.edit') }}</a>
    @endcan
</div>
<p>{{ __('ui.role') }}: {{ __('ui.role_'.$account->role) }}</p>
<p>{{ __('ui.department') }}: {{ $account->department?->name ?? '—' }}</p>
<p>{{ __('ui.base_salary') }}: {{ number_format((float) $account->base_salary, 2) }}</p>
<p>@include('partials.status', ['status' => $account->status])</p>
@can('update', $account)
    @if($account->id !== auth()->id())
        <form method="POST" action="{{ route('admin.users.destroy', $account) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('ui.delete') }}</button></form>
    @endif
@endcan
@endsection
