@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h1 class="h3">{{ __('ui.notifications') }}</h1>
    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-outline-secondary btn-sm">{{ __('ui.read_all') }}</button></form>
</div>
<div class="list-group">
    @forelse($notifications as $notification)
        <div class="list-group-item {{ $notification->read_at ? '' : 'list-group-item-light' }}">
            <div class="fw-semibold">{{ $notification->data['title'] ?? '' }}</div>
            <div>{{ $notification->data['body'] ?? '' }}</div>
            <div class="small text-secondary">{{ $notification->created_at->diffForHumans() }}</div>
            @if(!$notification->read_at)
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="mt-2">@csrf<button class="btn btn-sm btn-zpi">{{ __('ui.mark_read') }}</button></form>
            @endif
        </div>
    @empty
        <div class="list-group-item empty-state border-0">
            <p class="empty-state-title">{{ __('ui.empty_notifications_title') }}</p>
            <p class="empty-state-body mb-0">{{ __('ui.empty_notifications_body') }}</p>
        </div>
    @endforelse
</div>
<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
