@php
    $anchorBase = request()->routeIs('home') ? '' : route('home');
@endphp

<footer class="site-footer">
    <div class="container footer-main">
        <div class="footer-brand">
            <x-brand-logo light />
            <p>{{ $brand['tagline'] }}</p>
        </div>
        <div class="footer-links">
            <div>
                <strong>استكشف</strong>
                <a href="{{ $anchorBase }}#method">المنهجية</a>
                <a href="{{ route('tools.index') }}">الأدوات</a>
                <a href="{{ $anchorBase }}#about">عن خالد</a>
                <a href="{{ $anchorBase }}#knowledge">المعرفة</a>
            </div>
            <div>
                <strong>ابدأ</strong>
                @auth
                    <a href="{{ route('app.dashboard') }}">لوحة العمل</a>
                    <a href="{{ route('app.tools.index') }}">أدواتي</a>
                @else
                    <a href="{{ route('register') }}">إنشاء حساب</a>
                    <a href="{{ route('login') }}">تسجيل الدخول</a>
                @endauth
                <a href="{{ $anchorBase }}#faq">كيف تعمل المنصة؟</a>
            </div>
            <div>
                <strong>تواصل</strong>
                <a dir="ltr" href="tel:{{ $brand['contact']['phone'] }}">{{ $brand['contact']['phone_display'] }}</a>
                <a href="{{ $brand['contact']['whatsapp'] }}" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                <a href="{{ $brand['contact']['linkedin'] }}" target="_blank" rel="noopener noreferrer">LinkedIn</a>
                <a href="{{ $brand['contact']['x'] }}" target="_blank" rel="noopener noreferrer">X / Twitter</a>
            </div>
            <div>
                <strong>الموقع</strong>
                <span>{{ $brand['location'] }}</span>
                <a href="{{ route('privacy') }}">معلوماتك وخصوصيتك</a>
                <a href="{{ route('terms') }}">شروط الاستخدام</a>
            </div>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>© {{ date('Y') }} خالد سعد. جميع الحقوق محفوظة.</span>
        <span>صُمّم لاتخاذ قرار أوضح، لا لإضافة ضوضاء جديدة.</span>
    </div>
</footer>
