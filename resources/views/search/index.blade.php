@extends('layouts.app')
@section('title', __('ui.search'))
@section('content')
<h1 class="h3">{{ __('ui.search') }}</h1>
@if(mb_strlen($term) < 2)
    <p class="text-secondary">{{ __('ui.search_prompt') }}</p>
@elseif($groups === [])
    <div class="empty-state">
        <p class="empty-state-title">{{ __('ui.search_empty') }}</p>
        <p class="empty-state-body">{{ __('ui.search_hint') }}</p>
    </div>
@else
    @foreach($groups as $group)
        <h2 class="h5 mt-4">{{ $group['label'] }}</h2>
        <div class="list-group">
            @foreach($group['items'] as $item)
                <a class="list-group-item list-group-item-action" href="{{ $item['url'] }}">
                    <div class="fw-semibold">{{ $item['title'] }}</div>
                    @if($item['meta'] !== '')<div class="small text-secondary">{{ $item['meta'] }}</div>@endif
                </a>
            @endforeach
        </div>
    @endforeach
@endif
@endsection
