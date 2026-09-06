@extends('layouts.app')
@section('layout', 'reading')

@section('title', 'تفاصيل زيارة')

@php($seconds = fn (int $value) => \App\Modules\Insights\Models\VisitorSession::secondsForHumans($value))

@push('head')
    {{-- أنماط الإحصاءات خارج حزمة Vite: تُنشر مع الوحدة بلا انتظار بناء أصول. --}}
    <link rel='stylesheet' href='{{ asset('css/insights.css') }}?v={{ @filemtime(public_path('css/insights.css')) ?: 1 }}'>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">إحصاءات الزوّار</p>
            <h1>زيارة {{ $session->started_at->translatedFormat('j F Y — H:i') }}</h1>
            <p class="muted">
                <a href="{{ route('admin.insights.visitor', $session->visitor_id) }}">
                    {{ $session->user?->name ?? 'زائر '.substr($session->visitor_id, 0, 8) }}
                </a>
                — {{ $session->is_returning ? 'زائر عائد' : 'زائر جديد' }}
                @if ($session->last_activity_at->gt(now()->subMinutes(5)))
                    · <span class="live-dot" aria-hidden="true"></span>نشط الآن
                @endif
            </p>
        </div>
    </header>

    <section class="layout-metrics" aria-label="ملخّص الزيارة">
        <article class="stat">
            <span class="stat__value">{{ $session->page_views_count }}</span>
            <span class="stat__label">صفحة</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $seconds($session->active_seconds) }}</span>
            <span class="stat__label">وقت نشط</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $session->events_count }}</span>
            <span class="stat__label">تفاعل</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $channel_label }}</span>
            <span class="stat__label">القناة</span>
        </article>
        {{-- بأي دليل عُدّت هذه زيارةً بشرية: يُعرض لأن كل رقم يُبنى عليه. --}}
        <article class="stat">
            <span class="stat__value">
                @if (! $session->is_verified)
                    غير متحقَّقة
                @elseif ($session->verified_by === 'form')
                    نموذج مُرسَل
                @else
                    نبض المتصفح
                @endif
            </span>
            <span class="stat__label">أساس التحقّق</span>
        </article>
    </section>

    @unless ($session->is_verified)
        <p class="alert">
            لم تصل من هذه الزيارة أي إشارة من المتصفح، فهي خارج كل رقم في اللوحة.
            آلةٌ في الغالب الأعمّ، وقد تكون متصفّحًا عُطّل فيه جافاسكربت
            <span class="badge badge--assumption">فرضية</span>.
        </p>
    @endunless

    <div class="layout-main-aside">
        <div class="layout-flow">
            {{--
                الخط الزمني: الصفحات والأحداث مدموجة بترتيب وقوعها.

                فصلهما يُخفي القرار: «فتح التسعير ← ضغط ابدأ ← رجع للرئيسية»
                قصة كاملة، وقائمتان منفصلتان تجعلانها ثلاث حقائق بلا رابط.
            --}}
            <section aria-labelledby="journey-heading">
                <h2 id="journey-heading" class="section-title">الرحلة داخل الموقع</h2>

                <ol class="journey">
                    @foreach ($timeline as $step)
                        <li @class([
                            'journey__item',
                            'journey__item--event' => $step['type'] === 'event',
                            'journey__item--conversion' => ($step['is_conversion'] ?? false),
                        ])>
                            <div class="journey__head">
                                <time class="journey__time">{{ $step['at']->format('H:i:s') }}</time>
                                @if ($step['type'] === 'page')
                                    <strong><span class="path-cell" title="{{ $step['path'] }}">{{ $step['path'] }}</span></strong>
                                    @if (($step['status'] ?? 200) >= 400)
                                        <span class="badge badge--warn">{{ $step['status'] }}</span>
                                    @endif
                                @else
                                    <strong>{{ $step['label'] ?: $step['name'] }}</strong>
                                    @if ($step['is_conversion'])
                                        <span class="badge">تحويل</span>
                                    @endif
                                @endif
                            </div>

                            @if ($step['type'] === 'page')
                                <div class="journey__meta">
                                    <span>بقي {{ $seconds((int) $step['seconds']) }}</span>
                                    <span>مرّر {{ $step['scroll'] }}٪</span>
                                    @if ($step['response_ms'])
                                        <span>استجابة {{ $step['response_ms'] }}ms</span>
                                    @endif
                                </div>
                            @else
                                <div class="journey__meta">
                                    <span class="path-cell">{{ $step['path'] }}</span>
                                    <span>{{ $step['category'] }}</span>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>

                @if ($session->page_views_count > 0 && $session->active_seconds === 0)
                    {{-- فجوة تُعلن ولا تُخفى (§٤.٣). --}}
                    <p class="muted">
                        لا زمن مقيس لهذه الزيارة: غادر الزائر قبل أول نبضة، أو منع متصفحه تنفيذ السكربتات.
                        الصفحات مؤكدة من الخادم، والزمن وحده غائب.
                    </p>
                @endif
            </section>
        </div>

        <aside class="layout-aside layout-flow" aria-label="سياق الزيارة">
            <section aria-labelledby="context-heading">
                <h2 id="context-heading" class="section-title">السياق</h2>
                <div class="table-wrap">
                    <table class="table" data-table="matrix">
                        <tbody>
                            <tr><th>المصدر</th><td>{{ $session->platform ?? $session->referrer_host ?? 'بلا مُحيل معلن' }}</td></tr>
                            <tr><th>الرابط المُحيل</th><td class="path-cell">{{ $session->referrer_url ?? '—' }}</td></tr>
                            <tr><th>الحملة</th><td>{{ $session->campaign ?? '—' }}</td></tr>
                            <tr><th>المصدر/الوسيط</th><td>{{ $session->source ?? '—' }} / {{ $session->medium ?? '—' }}</td></tr>
                            <tr><th>الجهاز</th><td>{{ $session->device_type }}</td></tr>
                            <tr><th>المتصفح</th><td>{{ $session->browser ?? '—' }} {{ $session->browser_version }}</td></tr>
                            <tr><th>النظام</th><td>{{ $session->os ?? '—' }} {{ $session->os_version }}</td></tr>
                            <tr><th>الشاشة</th><td>{{ $session->screen_width ? $session->screen_width.'×'.$session->screen_height : '—' }}</td></tr>
                            <tr><th>نافذة العرض</th><td>{{ $session->viewport_width ? $session->viewport_width.'×'.$session->viewport_height : '—' }}</td></tr>
                            <tr><th>اللغة</th><td>{{ $session->language ?? '—' }}</td></tr>
                            <tr><th>المنطقة الزمنية</th><td>{{ $session->timezone ?? '—' }}</td></tr>
                            <tr>
                                <th>البلد</th>
                                <td>{{ $country_label }} <span class="badge badge--assumption">فرضية</span></td>
                            </tr>
                            <tr><th>معرّف الزيارة</th><td><code>{{ $session->uuid }}</code></td></tr>
                        </tbody>
                    </table>
                </div>

                <p class="muted">
                    عنوان IP غير مخزَّن خامًا — تُحفظ منه بصمة مُجزَّأة لا تُعكس، وتُستخدم للتعرّف الاحتياطي فقط.
                </p>
            </section>
        </aside>
    </div>
@endsection
