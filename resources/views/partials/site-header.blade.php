@php
    // على الصفحة الرئيسية الروابط مراسٍ داخلية، وفي الصفحات الأخرى تعود إلى الرئيسية.
    $anchorBase = request()->routeIs('home') ? '' : route('home');
    $startUrl = auth()->check()
        ? route('app.dashboard')
        : route('register', $startTool ?? []);
    $startLabel = auth()->check() ? 'لوحة العمل' : 'ابدأ الآن';
@endphp

<header class="site-header" data-site-header>
    <div class="container nav-shell">
        <a class="brand-link" href="{{ $anchorBase === '' ? '#top' : route('home') }}" aria-label="خالد سعد — الصفحة الرئيسية">
            <x-brand-logo />
        </a>

        <nav class="desktop-nav" aria-label="التنقل الرئيسي">
            <a href="{{ $anchorBase }}#method">المنهجية</a>
            <a href="{{ route('tools.index') }}" @class(['is-active' => request()->routeIs('tools.*')])>الأدوات</a>
            <a href="{{ $anchorBase }}#about">عن خالد</a>
            <a href="{{ $anchorBase }}#knowledge">المعرفة</a>
            <a href="{{ $anchorBase }}#faq">الأسئلة الشائعة</a>
        </nav>

        <div class="nav-actions">
            @guest
                <a class="nav-login" href="{{ route('login') }}">دخول</a>
            @endguest
            <a class="button button--primary nav-cta" href="{{ $startUrl }}">{{ $startLabel }}</a>
        </div>

        <button class="menu-toggle" type="button" aria-label="فتح القائمة" aria-expanded="false" aria-controls="mobile-menu" data-menu-toggle>
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>

    <nav id="mobile-menu" class="mobile-menu" aria-label="تنقل الجوال" data-mobile-menu hidden>
        <a href="{{ $anchorBase }}#method">المنهجية</a>
        <a href="{{ route('tools.index') }}">الأدوات</a>
        <a href="{{ $anchorBase }}#about">عن خالد</a>
        <a href="{{ $anchorBase }}#knowledge">المعرفة</a>
        <a href="{{ $anchorBase }}#faq">الأسئلة الشائعة</a>
        @guest
            <a href="{{ route('login') }}">تسجيل الدخول</a>
        @endguest
        <a class="button button--primary" href="{{ $startUrl }}">{{ $startLabel }}</a>
    </nav>
</header>
