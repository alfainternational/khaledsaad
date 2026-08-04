@php
    // على الصفحة الرئيسية الروابط مراسٍ داخلية، وفي الصفحات الأخرى تعود إلى الرئيسية.
    $anchorBase = request()->routeIs('home') ? '' : route('home');
    $startUrl = auth()->check()
        ? route('app.dashboard')
        : route('register', $startTool ?? []);
    $startLabel = auth()->check() ? 'لوحة التحكم' : 'ابدأ تشخيص مشروعك';
@endphp

<header class="site-header" data-site-header>
    <div class="container nav-shell">
        <a class="brand-link" href="{{ $anchorBase === '' ? '#top' : route('home') }}" aria-label="خالد سعد — الصفحة الرئيسية">
            <x-brand-logo />
        </a>

        <nav class="desktop-nav" aria-label="التنقل الرئيسي">
            <a href="{{ route('methodology') }}" @class(['is-active' => request()->routeIs('methodology')])>المنهجية</a>
            <a href="{{ route('tools.index') }}" @class(['is-active' => request()->routeIs('tools.*')])>التشخيصات</a>
            {{-- الفهرس لا قطاعًا بعينه: رابطٌ يذهب إلى واحد يجعل الاثنين
                 الآخرين حاشيةً في ذهن الزائر قبل أن يقرأ سطرًا. --}}
            <a href="{{ route('sectors.index') }}" @class(['is-active' => request()->routeIs('sectors.*')])>قطاعاتنا</a>
            <a href="{{ route('pricing') }}" @class(['is-active' => request()->routeIs('pricing')])>الأسعار</a>
            <a href="{{ route('profile') }}" @class(['is-active' => request()->routeIs('profile')])>السيرة</a>
            {{-- «مقالات» تصف ما خلف الرابط؛ «المعرفة» تسمية مجرّدة لا يعرف
                 القارئ ماذا يجد تحتها فلا ينقر. --}}
            <a href="{{ route('knowledge') }}" @class(['is-active' => request()->routeIs('knowledge')])>المعرفة</a>
            <a href="{{ route('faq') }}" @class(['is-active' => request()->routeIs('faq')])>الأسئلة الشائعة</a>
        </nav>

        <div class="nav-actions">
            @include('partials.theme-toggle')
            @guest
                <a class="nav-login" href="{{ route('login') }}">تسجيل الدخول</a>
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
        <div class="container mobile-menu__inner">
            <div class="mobile-menu__utilities">
                <span>مظهر الموقع</span>
                @include('partials.theme-toggle')
            </div>
            <a href="{{ route('methodology') }}">المنهجية</a>
            <a href="{{ route('tools.index') }}">التشخيصات</a>
            <a href="{{ route('services') }}">الخدمات والمخرجات</a>
            <a href="{{ route('profile') }}">السيرة</a>
            <a href="{{ route('knowledge') }}">المعرفة</a>
            <a href="{{ route('faq') }}">الأسئلة الشائعة</a>
            @guest
                <a href="{{ route('login') }}">تسجيل الدخول</a>
            @endguest
            <a class="button button--primary" href="{{ $startUrl }}">{{ $startLabel }}</a>
        </div>
    </nav>
</header>
