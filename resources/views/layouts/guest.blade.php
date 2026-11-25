<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ZPI')</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;600;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center py-5" style="min-height: 100vh; background: #0f3d32;">
    <div class="container">
        <div class="mx-auto auth-card">
            <div class="text-center text-white mb-4">
                <div class="fs-3 fw-bold" style="letter-spacing: .12em;">ZPI</div>
                <div>{{ __('ui.tagline') }}</div>
            </div>
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('locale.update') }}" class="mb-3">
                        @csrf
                        <label class="visually-hidden" for="guest-locale">{{ __('ui.language') }}</label>
                        <select id="guest-locale" name="locale" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="en" @selected(app()->getLocale() === 'en')>{{ __('ui.english') }}</option>
                            <option value="am" @selected(app()->getLocale() === 'am')>{{ __('ui.amharic') }}</option>
                        </select>
                    </form>
                    @include('partials.flash')
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
</body>
</html>
