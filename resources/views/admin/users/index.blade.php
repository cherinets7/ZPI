@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h1 class="h3">{{ __('ui.users') }}</h1>
    <a class="btn btn-zpi" href="{{ route('admin.users.create') }}">{{ __('ui.create') }}</a>
</div>
<div class="table-responsive card"><table class="table mb-0">
    <thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.email') }}</th><th>{{ __('ui.role') }}</th><th>{{ __('ui.department') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($users as $account)
        <tr>
            <td>{{ $account->name }}</td>
            <td>{{ $account->email }}</td>
            <td>{{ __('ui.role_'.$account->role) }}</td>
            <td>{{ $account->department?->name ?? '—' }}</td>
            <td>@include('partials.status', ['status' => $account->status])</td>
            <td>
                <a href="{{ route('admin.users.edit', $account) }}">{{ __('ui.edit') }}</a>
                @if($account->id !== auth()->id() && $account->status === 'active')
                    <form class="d-inline" method="POST" action="{{ route('admin.users.destroy', $account) }}">@csrf @method('DELETE')<button class="btn btn-link btn-sm">{{ __('ui.deactivate') }}</button></form>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table></div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
