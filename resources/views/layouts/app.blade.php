<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $institutionName ?? 'ZPI')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;600;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-3 col-xl-2 app-sidebar d-none d-lg-block p-3">
            @include('partials.sidebar')
        </aside>
        <div class="col-lg-9 col-xl-10 px-0">
            <header class="topbar d-flex align-items-center justify-content-between px-3 py-2">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <div class="fw-semibold">{{ $institutionName ?? 'ZPI' }}</div>
                        <div class="small text-secondary">{{ auth()->user()->name }} · {{ __('ui.role_'.auth()->user()->role) }}</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <form method="POST" action="{{ route('locale.update') }}" class="d-flex">
                        @csrf
                        <select name="locale" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="en" @selected(app()->getLocale() === 'en')>{{ __('ui.english') }}</option>
                            <option value="am" @selected(app()->getLocale() === 'am')>{{ __('ui.amharic') }}</option>
                        </select>
                    </form>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary position-relative" data-bs-toggle="dropdown" type="button">
                            <i class="bi bi-bell"></i>
                            @if(($unreadNotifications ?? collect())->count())
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $unreadNotifications->count() }}</span>
                            @endif
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 280px;">
                            @forelse($unreadNotifications ?? [] as $notification)
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    <button class="dropdown-item text-wrap" type="submit">
                                        <strong>{{ $notification->data['title'] ?? '' }}</strong>
                                        <div class="small text-secondary">{{ $notification->data['body'] ?? '' }}</div>
                                    </button>
                                </form>
                            @empty
                                <div class="px-2 py-1 text-secondary small">{{ __('ui.notification_empty') }}</div>
                            @endforelse
                            <a class="dropdown-item text-center small" href="{{ route('notifications.index') }}">{{ __('ui.notifications') }}</a>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-zpi btn-sm" type="submit">{{ __('ui.logout') }}</button>
                    </form>
                </div>
            </header>
            <main class="p-3 p-lg-4">
                @include('partials.flash')
                @yield('content')
            </main>
        </div>
    </div>
</div>
<div class="offcanvas offcanvas-start app-sidebar" tabindex="-1" id="mobileNav">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title brand-mark">ZPI</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        @include('partials.sidebar')
    </div>
</div>
</body>
</html>
