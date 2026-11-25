@php($theme = auth()->user()->theme === 'dark' ? 'dark' : 'light')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $institutionName ?? 'ZPI')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;600;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main">{{ __('ui.skip') }}</a>
<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-3 col-xl-2 app-sidebar d-none d-lg-block p-3">
            @include('partials.sidebar')
        </aside>
        <div class="col-lg-9 col-xl-10 px-0">
            <header class="topbar d-flex align-items-center justify-content-between px-3 py-2">
                <div class="d-flex align-items-center gap-2 min-w-0">
                    <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="{{ __('ui.menu') }}">
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>
                    <div class="min-w-0">
                        <div class="fw-semibold text-truncate">{{ $institutionName ?? 'ZPI' }}</div>
                        <div class="small text-secondary text-truncate">{{ auth()->user()->name }} · {{ __('ui.role_'.auth()->user()->role) }}</div>
                    </div>
                </div>
                <div class="header-actions d-flex align-items-center gap-2">
                    <form method="GET" action="{{ route('search') }}" class="search-form position-relative" role="search" data-search>
                        <label class="visually-hidden" for="quick-search">{{ __('ui.search') }}</label>
                        <input id="quick-search" class="form-control form-control-sm" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search') }}" autocomplete="off" data-search-url="{{ route('search') }}" data-empty="{{ __('ui.search_empty') }}">
                        <div class="search-panel d-none" id="quick-search-panel" role="listbox" aria-label="{{ __('ui.search') }}"></div>
                    </form>
                    <form method="POST" action="{{ route('theme.update') }}" data-theme-toggle>
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="theme" value="{{ $theme === 'dark' ? 'light' : 'dark' }}">
                        <button class="btn btn-outline-secondary" type="submit" aria-pressed="{{ $theme === 'dark' ? 'true' : 'false' }}" aria-label="{{ $theme === 'dark' ? __('ui.theme_light') : __('ui.theme_dark') }}" data-label-light="{{ __('ui.theme_light') }}" data-label-dark="{{ __('ui.theme_dark') }}">
                            <i class="bi bi-{{ $theme === 'dark' ? 'sun' : 'moon' }}" aria-hidden="true"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('locale.update') }}" class="d-flex">
                        @csrf
                        <label class="visually-hidden" for="locale-switch">{{ __('ui.language') }}</label>
                        <select id="locale-switch" name="locale" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="en" @selected(app()->getLocale() === 'en')>{{ __('ui.english') }}</option>
                            <option value="am" @selected(app()->getLocale() === 'am')>{{ __('ui.amharic') }}</option>
                        </select>
                    </form>
                    <div class="dropdown" id="notification-bell" data-feed-url="{{ route('notifications.feed') }}" data-empty="{{ __('ui.notification_empty') }}" data-all="{{ __('ui.notifications') }}" data-all-url="{{ route('notifications.index') }}" data-token="{{ csrf_token() }}">
                        <button class="btn btn-outline-secondary position-relative" data-bs-toggle="dropdown" type="button" aria-label="{{ __('ui.notifications') }}">
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger {{ ($unreadNotifications ?? collect())->count() ? '' : 'd-none' }}" data-notification-count aria-live="polite">{{ ($unreadNotifications ?? collect())->count() }}</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-2" data-notification-menu>
                            <div data-notification-list>
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
                            </div>
                            <a class="dropdown-item text-center small" href="{{ route('notifications.index') }}">{{ __('ui.notifications') }}</a>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-zpi btn-sm" type="submit">{{ __('ui.logout') }}</button>
                    </form>
                </div>
            </header>
            <main id="main" class="p-3 p-lg-4">
                @include('partials.flash')
                @yield('content')
            </main>
        </div>
    </div>
</div>
<div class="offcanvas offcanvas-start app-sidebar" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title brand-mark h5" id="mobileNavLabel">ZPI</h2>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="{{ __('ui.close') }}"></button>
    </div>
    <div class="offcanvas-body">
        @include('partials.sidebar', ['mobile' => true])
    </div>
</div>
</body>
</html>
