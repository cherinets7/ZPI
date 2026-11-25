<div class="mb-4">
    @if(!empty($institutionLogo))
        <img src="{{ asset('storage/'.$institutionLogo) }}" alt="" class="mb-2" style="max-height: 42px;">
    @endif
    <div class="brand-mark">ZPI</div>
    <div class="small" style="opacity:.8">{{ $institutionName ?? __('ui.tagline') }}</div>
</div>
<nav class="nav flex-column">
    @foreach($navItems ?? [] as $item)
        <a class="nav-link {{ request()->routeIs($item['active']) ? 'active' : '' }}" href="{{ route($item['route'], $item['query'] ?? []) }}" @if(request()->routeIs($item['active'])) aria-current="page" @endif @if(!empty($mobile)) data-bs-dismiss="offcanvas" @endif>
            <i class="bi bi-{{ $item['icon'] }} me-2" aria-hidden="true"></i>{{ $item['label'] }}
        </a>
    @endforeach
</nav>
