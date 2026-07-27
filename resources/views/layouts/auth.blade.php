<!doctype html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#071F5B">
        <meta name="robots" content="noindex, nofollow">
        <title>@yield('title', 'الحساب') — خالد سعد</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @include('partials.font')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php($layoutFamily = trim($__env->yieldContent('layout', 'auth')))
    <body class="auth-page" data-layout="{{ $layoutFamily }}">
        <a class="skip-link" href="#main-content">تجاوز إلى المحتوى</a>

        <main id="main-content" class="auth-card layout-page layout-page--reading">
            <a href="{{ route('home') }}" class="auth-card__brand"><x-brand-logo /></a>

            <h1>@yield('heading')</h1>
            <p class="auth-card__lead">@yield('lead')</p>

            @yield('context')

            @if (session('status'))
                <p class="alert alert--success" role="status">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <div class="alert alert--error" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('form')

            <p class="auth-card__alt">@yield('alt')</p>
        </main>
    </body>
</html>
