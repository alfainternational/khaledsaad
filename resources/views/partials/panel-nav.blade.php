{{-- تنقّل السايدبار الموحّد للوحتين: روابط الإدارة داخل admin.* وروابط العمل خارجها --}}
@php($isAdminArea = request()->routeIs('admin.*'))

@if ($isAdminArea)
    <nav class="panel__nav" aria-label="تنقّل الإدارة">
        <p class="panel__nav-label">عام</p>

        <a href="{{ route('admin.dashboard') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.dashboard')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/></svg>
            <span>نظرة عامة</span>
        </a>

        <a href="{{ route('admin.operations') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.operations')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            <span>غرفة العمليات</span>
        </a>

        <a href="{{ route('admin.insights') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.insights') || request()->routeIs('admin.insights.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"/><path d="m7 14 3-4 3 3 5-7"/></svg>
            <span>إحصاءات الزوّار</span>
        </a>

        <a href="{{ route('admin.usage') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.usage')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/></svg>
            <span>التكلفة</span>
        </a>

        <p class="panel__nav-label">المنصة</p>

        <a href="{{ route('admin.tools.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.tools.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
            <span>الأدوات</span>
        </a>

        <a href="{{ route('admin.reporting-gaps.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.reporting-gaps.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
            <span>فجوات التقارير</span>
        </a>

        <a href="{{ route('admin.content.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.content.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
            <span>المحتوى</span>
        </a>

        <a href="{{ route('admin.content-subscribers.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.content-subscribers.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
            <span>مشتركو المحتوى</span>
        </a>

        <a href="{{ route('admin.content-media.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.content-media.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m5 18 4-4 3 3 3-4 4 5"/></svg>
            <span>مكتبة الوسائط</span>
        </a>

        <a href="{{ route('admin.consultations.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.consultations.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 4h16v12H8l-4 4z"/><path d="M8 8h8M8 12h5"/></svg>
            <span>الاستشارة الذكية</span>
        </a>

        <a href="{{ route('admin.users.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.users.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.2 3.4-5 6.5-5s5.7 1.8 6.5 5"/><circle cx="17" cy="9" r="2.5"/><path d="M17.5 15c2.3.3 3.6 1.7 4 4"/></svg>
            <span>المستخدمون</span>
        </a>

        <a href="{{ route('admin.manual.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.manual.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h9l5 5v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5"/><path d="m9 14 2 2 4-4"/></svg>
            <span>المراجعة اليدوية</span>
        </a>

        <p class="panel__nav-label">الفوترة</p>

        <a href="{{ route('admin.plans.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.plans.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 2 9 5-9 5-9-5z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/></svg>
            <span>الخطط</span>
        </a>

        <a href="{{ route('admin.features.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.features.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <span>فهرس الميزات</span>
        </a>

        <a href="{{ route('admin.packs.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.packs.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/></svg>
            <span>حزم الأرصدة</span>
        </a>

        <a href="{{ route('admin.gateways.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.gateways.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>
            <span>بوابات الدفع</span>
        </a>

        <a href="{{ route('admin.payments.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.payments.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h14v18l-2.3-1.5L14.4 21l-2.4-1.5L9.6 21l-2.3-1.5L5 21z"/><path d="M9 8h6"/><path d="M9 12h6"/></svg>
            <span>المدفوعات</span>
        </a>

        <p class="panel__nav-label">النظام</p>

        <a href="{{ route('admin.settings') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.settings*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .32 1.77l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.6 1.6 0 0 0-1.77-.32 1.6 1.6 0 0 0-.97 1.47V21a2 2 0 1 1-4 0v-.09a1.6 1.6 0 0 0-1.05-1.47 1.6 1.6 0 0 0-1.77.32l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.6 1.6 0 0 0 4.6 15a1.6 1.6 0 0 0-1.47-.97H3a2 2 0 1 1 0-4h.09A1.6 1.6 0 0 0 4.56 9a1.6 1.6 0 0 0-.32-1.77l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.6 1.6 0 0 0 1.77.32H9a1.6 1.6 0 0 0 .97-1.47V3a2 2 0 1 1 4 0v.09a1.6 1.6 0 0 0 .97 1.47 1.6 1.6 0 0 0 1.77-.32l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.6 1.6 0 0 0-.32 1.77V9c.3.6.86 1 1.47.97H21a2 2 0 1 1 0 4h-.09a1.6 1.6 0 0 0-1.51 1.03Z"/></svg>
            <span>الإعدادات والمفاتيح</span>
        </a>
    </nav>
@else
    @php($panelExperience = auth()->user()?->activeExperience() ?? \App\Support\Experience\Experience::BUSINESS)
    <nav class="panel__nav" aria-label="{{ __('التنقل الرئيسي') }}">
        <p class="panel__nav-label">
            {{ $panelExperience === \App\Support\Experience\Experience::LEARNING
                ? __('التعلم بالتطبيق')
                : __('تحسين المشروع') }}
        </p>

        {{-- القائمة تُقرأ من السجل لا تُكتب هنا: عنصرٌ يشير إلى وجهة عنصرٍ
             آخر صار خطأً يكشفه اختبار، بدل أن يمرّ في المراجعة (INV-6). --}}
        @foreach (\App\Support\Navigation\NavRegistry::primary($panelExperience) as $item)
            @if ($item->isAvailable())
                <a href="{{ $item->url() }}" @class(['panel__link', 'is-active' => $item->isActive()])>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h6"/></svg>
                    <span>{{ $item->label }}</span>
                </a>
            @else
                {{-- لا رابط يعمل ويكذب: العنصر غير الجاهز يُرسم معطّلًا
                     بشارته، ولا يُوجَّه إلى بديل يشبهه. --}}
                <span class="panel__link is-disabled" aria-disabled="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h6"/></svg>
                    <span>{{ $item->label }}</span>
                    @if ($item->badge)<em class="panel__link-badge">{{ $item->badge }}</em>@endif
                </span>
            @endif
        @endforeach

        @if ($panelExperience === \App\Support\Experience\Experience::BUSINESS)
            <a href="{{ route('app.consultations.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('app.consultations.*')])>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 4h16v12H8l-4 4z"/></svg>
                <span>{{ __('ساعدني أختار من أين أبدأ') }}</span>
            </a>
        @endif

        <p class="panel__nav-label">{{ __('الحساب') }}</p>

        <a href="{{ route('app.experience.choose') }}" @class(['panel__link', 'is-active' => request()->routeIs('app.experience.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 7h12l-3-3m3 3-3 3M17 17H5l3 3m-3-3 3-3"/></svg>
            <span>{{ __('تغيير ما أعمل عليه الآن') }}</span>
        </a>

        @if ($panelExperience === \App\Support\Experience\Experience::BUSINESS)
            <a href="{{ route('app.billing') }}" @class(['panel__link', 'is-active' => request()->routeIs('app.billing') || request()->routeIs('app.checkout.*')])>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/></svg>
                <span>{{ __('الاشتراك والفوترة') }}</span>
            </a>
        @endif

        <a href="{{ route('app.security') }}" @class(['panel__link', 'is-active' => request()->routeIs('app.security*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6.5V11c0 5 3.4 8.6 8 10 4.6-1.4 8-5 8-10V6.5z"/></svg>
            <span>{{ __('أمان الحساب') }}</span>
        </a>
    </nav>
@endif
