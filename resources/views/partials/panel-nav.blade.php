{{-- تنقّل السايدبار الموحّد للوحتين: روابط الإدارة داخل admin.* وروابط العمل خارجها --}}
@php($isAdminArea = request()->routeIs('admin.*'))

@if ($isAdminArea)
    <nav class="panel__nav" aria-label="تنقّل الإدارة">
        <p class="panel__nav-label">عام</p>

        <a href="{{ route('admin.dashboard') }}" @class(['panel__link', 'is-active' => request()->routeIs('admin.dashboard')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/></svg>
            <span>نظرة عامة</span>
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
    <nav class="panel__nav" aria-label="التنقل الرئيسي">
        <p class="panel__nav-label">لوحة التحكم</p>

        <a href="{{ route('app.dashboard') }}" @class(['panel__link', 'is-active' => request()->routeIs('app.dashboard')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/></svg>
            <span>النظرة العامة</span>
        </a>

        <a href="{{ route('app.projects.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('app.projects.*') || request()->routeIs('app.tasks.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
            <span>المشاريع</span>
        </a>

        <a href="{{ route('app.tools.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('app.tools.*') || request()->routeIs('app.runs.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
            <span>التشخيصات</span>
        </a>

        <a href="{{ route('app.consultations.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('app.consultations.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 4h16v12H8l-4 4z"/><path d="M8 8h8M8 12h5"/></svg>
            <span>التشخيص الذكي الشامل</span>
        </a>

        @feature(\App\Support\Billing\FeatureKey::GROWTH_PULSE)
            <a href="{{ route('app.pulse.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('app.pulse.*')])>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12h4l3-8 6 16 3-8h4"/></svg>
                <span>النبض الأسبوعي</span>
            </a>
        @endfeature

        <a href="{{ route('app.notifications.index') }}" @class(['panel__link', 'is-active' => request()->routeIs('app.notifications.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            <span>الإشعارات</span>
            @if (($panelUnread ?? 0) > 0)
                <b class="panel__link-count">{{ $panelUnread > 9 ? '9+' : $panelUnread }}</b>
            @endif
        </a>

        <p class="panel__nav-label">الحساب</p>

        <a href="{{ route('app.billing') }}" @class(['panel__link', 'is-active' => request()->routeIs('app.billing') || request()->routeIs('app.checkout.*')])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>
            <span>الأرصدة والاشتراك</span>
        </a>
    </nav>
@endif
