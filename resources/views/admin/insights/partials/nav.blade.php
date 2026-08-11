{{-- تنقّل الإحصاءات: المدة تُحمل مع الرابط فلا تُفقد عند الانتقال. --}}
<nav class="insights-nav" aria-label="أقسام الإحصاءات">
    <a href="{{ route('admin.insights', ['days' => $days ?? 30]) }}"
       @class(['is-active' => request()->routeIs('admin.insights')])>نظرة عامة</a>

    <a href="{{ route('admin.insights.visitors') }}"
       @class(['is-active' => request()->routeIs('admin.insights.visitors') || request()->routeIs('admin.insights.visitor')])>الزيارات</a>

    <a href="{{ route('admin.insights.visitors', ['live' => 1]) }}">
        <span class="live-dot" aria-hidden="true"></span>الآن
    </a>

    <a href="{{ route('admin.insights.export', ['days' => $days ?? 30]) }}">تصدير CSV</a>
</nav>
