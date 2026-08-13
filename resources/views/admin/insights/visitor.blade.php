@extends('layouts.app')
@section('layout', 'dashboard')

@section('title', 'ملف زائر')

@php($seconds = fn (int $value) => \App\Modules\Insights\Models\VisitorSession::secondsForHumans($value))

@push('head')
    {{-- أنماط الإحصاءات خارج حزمة Vite: تُنشر مع الوحدة بلا انتظار بناء أصول. --}}
    <link rel='stylesheet' href='{{ asset('css/insights.css') }}?v={{ @filemtime(public_path('css/insights.css')) ?: 1 }}'>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">إحصاءات الزوّار</p>
            <h1>{{ $profile['user']?->name ?? 'زائر '.substr($profile['visitor_id'], 0, 8) }}</h1>
            <p class="muted">
                @if ($profile['user'])
                    {{ $profile['user']->email }} — عُرف بعد تسجيل الدخول.
                @else
                    زائر غير مسجّل. لا نعرف اسمه ولا بريده، ونعرف رحلته كاملة.
                @endif
            </p>
        </div>
    </header>

    @include('admin.insights.partials.nav')

    <section class="layout-metrics" aria-label="ملخّص الزائر">
        <article class="stat">
            <span class="stat__value">{{ $profile['sessions_count'] }}</span>
            <span class="stat__label">زيارة</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $profile['page_views'] }}</span>
            <span class="stat__label">صفحة</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $seconds($profile['total_seconds']) }}</span>
            <span class="stat__label">إجمالي الوقت النشط</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $profile['conversions'] }}</span>
            <span class="stat__label">تحويل</span>
        </article>
    </section>

    {{--
        المصدر الأول لا الأخير.

        من عرف الموقع من مساعد ذكاء ثم عاد مباشرةً عشر مرات مصدره ذلك
        المساعد. نسبته إلى «مباشر» لأنها آخر زيارة يمحو القناة التي
        تستحق الميزانية فعلًا.
    --}}
    <section aria-labelledby="origin-heading">
        <h2 id="origin-heading" class="section-title">كيف عرفنا</h2>
        <div class="table-wrap">
            <table class="table" data-table="matrix">
                <tbody>
                    <tr><th>أول ظهور</th><td>{{ $profile['first_seen']->translatedFormat('j F Y — H:i') }} ({{ $profile['first_seen']->diffForHumans() }})</td></tr>
                    <tr><th>آخر نشاط</th><td>{{ $profile['last_seen']->translatedFormat('j F Y — H:i') }} ({{ $profile['last_seen']->diffForHumans() }})</td></tr>
                    <tr><th>القناة الأولى</th><td>{{ $profile['first_channel_label'] }}</td></tr>
                    <tr><th>المنصة</th><td>{{ $profile['first_platform'] ?? '—' }}</td></tr>
                    <tr><th>المُحيل</th><td>{{ $profile['first_referrer'] ?? 'بلا مُحيل معلن' }}</td></tr>
                    <tr><th>الحملة</th><td>{{ $profile['first_campaign'] ?? '—' }}</td></tr>
                    <tr><th>أول صفحة</th><td><span class="path-cell">{{ $profile['first_landing'] }}</span></td></tr>
                    <tr><th>الجهاز</th><td>{{ $profile['device'] }}</td></tr>
                    <tr>
                        <th>البلد</th>
                        <td>
                            {{ $profile['country'] }} <span class="badge badge--assumption">فرضية</span>
                            <span class="muted">
                                {{ match ($profile['location_basis']) {
                                    'timezone' => 'مستنتج من المنطقة الزمنية',
                                    'language' => 'مستنتج من لغة المتصفح — إشارة أضعف',
                                    default => 'لا إشارة موقع',
                                } }}
                            </span>
                        </td>
                    </tr>
                    <tr><th>معرّف الزائر</th><td><code>{{ $profile['visitor_id'] }}</code></td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="sessions-heading">
        <h2 id="sessions-heading" class="section-title">زياراته ({{ $profile['sessions_count'] }})</h2>
        <div class="table-wrap">
            <table class="table" data-table="matrix">
                <thead>
                    <tr><th>التاريخ</th><th>القناة</th><th>الدخول</th><th>الخروج</th><th>الصفحات</th><th>البقاء</th><th>النتيجة</th></tr>
                </thead>
                <tbody>
                    @foreach ($profile['sessions'] as $session)
                        <tr>
                            <td><a href="{{ route('admin.insights.session', $session->uuid) }}">{{ $session->started_at->format('Y-m-d H:i') }}</a></td>
                            <td>{{ $channels[$session->channel] ?? $session->channel }}</td>
                            <td><span class="path-cell" title="{{ $session->entry_path }}">{{ $session->entry_path }}</span></td>
                            <td><span class="path-cell" title="{{ $session->exit_path }}">{{ $session->exit_path ?? '—' }}</span></td>
                            <td>{{ $session->page_views_count }}</td>
                            <td>{{ $session->durationForHumans() }}</td>
                            <td>
                                @if ($session->conversion_name)
                                    {{ config('insights.conversion_events')[$session->conversion_name] ?? $session->conversion_name }}
                                @elseif ($session->is_bounce)
                                    <span class="muted">ارتداد</span>
                                @else
                                    <span class="muted">تصفّح</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
