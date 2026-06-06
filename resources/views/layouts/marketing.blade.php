<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description ?? 'منصة التسويق الاستراتيجي — من الفكرة إلى التنفيذ' }}">
    <meta name="theme-color" content="#6366f1">
    <title>{{ $title ?? 'خالد سعد — المنصة الاستراتيجية' }}</title>

    {{-- Preconnect for Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    {{-- Vite compiled assets (CSS + JS) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
<body>

    {{-- ═══ NAVIGATION ═══ --}}
    <div class="site-container site-nav-wrap">
        <header class="site-nav" id="main-nav" role="banner">
            {{-- Logo --}}
            <a href="{{ route('home') }}" class="nav-logo" aria-label="{{ config('app.name') }}">
                <div class="nav-logo-mark" aria-hidden="true">خ</div>
                <span class="nav-logo-name">خالد سعد</span>
            </a>

            {{-- Desktop nav links --}}
            <nav aria-label="القائمة الرئيسية">
                <ul class="nav-links" id="nav-links" role="list">
                    @foreach([
                        'الرئيسية'   => 'home',
                        'المسارات'  => 'paths.index',
                        'الأدوات'   => 'tools.index',
                    ] as $label => $route)
                    <li>
                        <a href="{{ route($route) }}"
                           class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}"
                           @if(request()->routeIs($route)) aria-current="page" @endif>
                            {{ $label }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </nav>

            {{-- Actions --}}
            <div class="nav-actions">
                {{-- Dark/Light toggle --}}
                <button class="theme-toggle" id="theme-toggle" data-theme-toggle aria-label="تبديل الوضع (داكن/فاتح)" title="تبديل الوضع">
                    {{-- Moon icon --}}
                    <svg class="icon-moon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                    {{-- Sun icon --}}
                    <svg class="icon-sun" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/>
                    </svg>
                </button>

                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm">لوحة العمل</a>
                    <form method="POST" action="{{ route('logout') }}" class="nav-inline-form">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">تسجيل الخروج</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">تسجيل الدخول</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">ابدأ مجاناً</a>
                @endauth

                {{-- Mobile toggle --}}
                <button class="nav-toggle" id="nav-toggle" data-nav-toggle aria-label="فتح القائمة" aria-expanded="false" aria-controls="nav-links">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </header>
    </div>

    {{-- ═══ MAIN CONTENT ═══ --}}
    <main id="main-content" tabindex="-1">
        @yield('content')
    </main>

    {{-- ═══ FOOTER ═══ --}}
    <footer class="site-footer" role="contentinfo">
        <div class="footer-glow" aria-hidden="true"></div>
        <div class="site-container">

            {{-- Top grid --}}
            <div class="footer-grid">
                {{-- Brand column --}}
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="nav-logo-mark" aria-hidden="true">خ</div>
                        <span class="footer-brand-name">خالد سعد</span>
                    </div>
                    <p class="footer-brand-desc">
                        المنصة الاستراتيجية العربية التي تحول فكرتك إلى خطوات تنفيذية واضحة ومترابطة.
                    </p>
                    <div class="footer-socials" aria-label="وسائل التواصل الاجتماعي">
                        @foreach([
                            ['href' => 'https://x.com/KhaledAASaad', 'label' => 'تويتر X', 'path' => 'M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z'],
                            ['href' => 'https://www.linkedin.com/in/khaledaasaad/', 'label' => 'لينكد إن',  'path' => 'M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z M4 6a2 2 0 100-4 2 2 0 000 4z'],
                            ['href' => '#', 'label' => 'يوتيوب',   'path' => 'M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 00-1.95 1.96A29 29 0 001 12a29 29 0 00.46 5.58A2.78 2.78 0 003.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 001.95-1.95A29 29 0 0023 12a29 29 0 00-.46-5.58z M9.75 15.02L15.5 12l-5.75-3.02v6.04z'],
                        ] as $s)
                        <a href="{{ $s['href'] }}" class="footer-social-btn" aria-label="{{ $s['label'] }}" title="{{ $s['label'] }}" @if(!str_starts_with($s['href'], '#')) target="_blank" rel="noopener noreferrer" @endif>
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="{{ $s['path'] }}"/>
                            </svg>
                        </a>
                        @endforeach
                    </div>
                </div>

                {{-- Link columns --}}
                <nav aria-label="روابط المنصة">
                    <h3 class="footer-col-title">المنصة</h3>
                    <ul class="footer-links" role="list">
                        <li><a href="{{ route('home') }}" class="footer-link">الرئيسية</a></li>
                        <li><a href="{{ route('paths.index') }}" class="footer-link">المسارات</a></li>
                        <li><a href="{{ route('tools.index') }}" class="footer-link">الأدوات</a></li>
                        <li><a href="{{ route('studio.index') }}" class="footer-link">الاستوديو</a></li>
                        <li><a href="{{ route('pricing') }}" class="footer-link">التسعير</a></li>
                    </ul>
                </nav>
                <nav aria-label="روابط المعرفة">
                    <h3 class="footer-col-title">المعرفة</h3>
                    <ul class="footer-links" role="list">
                        <li><a href="{{ route('blog.index') }}" class="footer-link">المدونة</a></li>
                        <li><a href="{{ route('case-studies.index') }}" class="footer-link">دراسات الحالة</a></li>
                        <li><a href="{{ route('community.index') }}" class="footer-link">المجتمع</a></li>
                        <li><a href="{{ route('templates.index') }}" class="footer-link">القوالب</a></li>
                    </ul>
                </nav>
                <nav aria-label="روابط الشركة">
                    <h3 class="footer-col-title">الشركة</h3>
                    <ul class="footer-links" role="list">
                        <li><a href="{{ route('about') }}" class="footer-link">عن خالد سعد</a></li>
                        <li><a href="{{ route('contact') }}" class="footer-link">تواصل معنا</a></li>
                        <li><a href="{{ route('privacy') }}" class="footer-link">سياسة الخصوصية</a></li>
                        <li><a href="{{ route('partnerships') }}" class="footer-link">الشراكات</a></li>
                    </ul>
                </nav>
            </div>

            {{-- Bottom bar --}}
            <div class="footer-bottom">
                <p class="footer-copy">© {{ date('Y') }} خالد سعد للاستشارات التسويقية. جميع الحقوق محفوظة.</p>
                <nav class="footer-legal" aria-label="روابط قانونية">
                    <a href="{{ route('privacy') }}">سياسة الخصوصية</a>
                    <a href="{{ route('terms') }}">شروط الاستخدام</a>
                </nav>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
