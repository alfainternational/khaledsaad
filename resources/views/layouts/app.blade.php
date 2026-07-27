<!doctype html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#071F5B">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php($isAdminArea = request()->routeIs('admin.*'))
        <title>@yield('title', $isAdminArea ? 'لوحة الإدارة' : 'لوحة التحكم') — خالد سعد</title>

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @include('partials.font')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="panel">
        <a class="skip-link" href="#main-content">تجاوز إلى المحتوى</a>

        @php($user = auth()->user())
        @php($panelUnread = $user->unreadNotifications()->count())
        @php($layoutFamily = trim($__env->yieldContent('layout', 'index')))

        {{-- السايدبار: العمود الثابت للتنقل في اللوحتين --}}
        <aside id="panel-sidebar" class="panel__side">
            <div class="panel__brand">
                <a href="{{ $isAdminArea ? route('admin.dashboard') : route('app.dashboard') }}" aria-label="{{ $isAdminArea ? 'لوحة الإدارة' : 'لوحة التحكم' }}">
                    <x-brand-logo light />
                </a>
                <span class="panel__brand-context">{{ $isAdminArea ? 'لوحة الإدارة' : 'لوحة التحكم' }}</span>
            </div>

            @include('partials.panel-nav')

            <div class="panel__side-foot">
                @if ($user->isAdmin())
                    @if ($isAdminArea)
                        <a href="{{ route('app.dashboard') }}" class="panel__switch">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3"/><path d="m15 7 5 5-5 5"/><path d="M20 12H8"/></svg>
                            <span>العودة إلى لوحة التحكم</span>
                        </a>
                    @else
                        <a href="{{ route('admin.dashboard') }}" class="panel__switch">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6.5V11c0 5 3.4 8.6 8 10 4.6-1.4 8-5 8-10V6.5z"/><path d="m9 12 2 2 4-4"/></svg>
                            <span>لوحة الإدارة</span>
                        </a>
                    @endif
                @endif

                <div class="panel__user">
                    <span class="panel__avatar" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
                    <span class="panel__user-meta">
                        <strong>{{ $user->name }}</strong>
                        <small>{{ $user->email }}</small>
                    </span>
                    <form method="POST" action="{{ route('logout') }}" class="panel__logout">
                        @csrf
                        <button type="submit" title="تسجيل الخروج" aria-label="تسجيل الخروج">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 3h3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-3"/><path d="m9 7-5 5 5 5"/><path d="M4 12h12"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="panel__backdrop" data-nav-backdrop hidden></div>

        <div class="panel__body">
            {{-- الهيدر العلوي: عنوان الصفحة + الإجراءات السريعة --}}
            <header class="panel__top">
                <button type="button" class="panel__burger" data-nav-toggle aria-controls="panel-sidebar" aria-expanded="false" aria-label="فتح قائمة التنقل">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/></svg>
                </button>

                <div class="panel__top-title">
                    <small>{{ $isAdminArea ? 'الإدارة' : 'مساحة العمل' }}</small>
                    <strong>@yield('title', $isAdminArea ? 'لوحة الإدارة' : 'لوحة التحكم')</strong>
                </div>

                <div class="panel__top-actions">
                    <a href="{{ route('app.notifications.index') }}" class="bell" aria-label="الإشعارات">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                        @if ($panelUnread > 0)
                            <span class="bell__count">{{ $panelUnread > 9 ? '9+' : $panelUnread }}</span>
                        @endif
                    </a>

                    @if (! $isAdminArea)
                        <a href="{{ route('app.projects.create') }}" class="btn btn--primary btn--sm panel__top-cta">أضف مشروعًا</a>
                    @endif
                </div>
            </header>

            <main id="main-content"
                class="panel__main layout-page layout-page--{{ in_array($layoutFamily, ['reading', 'auth'], true) ? 'reading' : (in_array($layoutFamily, ['form', 'wizard'], true) ? 'form' : 'operational') }}"
                data-layout="{{ $layoutFamily }}">
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

                @yield('content')
            </main>
        </div>

        <script>
            (function () {
                var body = document.body;
                var toggle = document.querySelector('[data-nav-toggle]');
                var backdrop = document.querySelector('[data-nav-backdrop]');

                function close() {
                    body.classList.remove('nav-open');
                    backdrop.hidden = true;
                    toggle.setAttribute('aria-expanded', 'false');
                }

                toggle.addEventListener('click', function () {
                    var open = body.classList.toggle('nav-open');
                    backdrop.hidden = ! open;
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                });

                backdrop.addEventListener('click', close);
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && body.classList.contains('nav-open')) {
                        close();
                    }
                });
            })();
        </script>

        @stack('scripts')
    </body>
</html>
