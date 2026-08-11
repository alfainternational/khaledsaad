@extends('layouts.app')
@section('layout', 'dashboard')

@section('title', 'إحصاءات الزوّار')

@php
    // بلا `use` داخل القالب: الاستيراد يُجمَّع داخل دالة العرض فيُسقطها.
    $seconds = fn (int $value) => \App\Modules\Insights\Models\VisitorSession::secondsForHumans($value);

    $delta = function (?float $value): array {
        if ($value === null) {
            return ['class' => 'delta--flat', 'text' => __('لا أساس للمقارنة')];
        }

        return [
            'class' => $value > 0 ? 'delta--up' : ($value < 0 ? 'delta--down' : 'delta--flat'),
            // الوصل يثبّت ترتيب الكلمات: الجملة كاملة مفتاحٌ واحد ونائبها
            // يحمل الإشارة، فتتحرر اللغة الهدف في ترتيبها.
            'text' => __(':delta٪ عن المدة السابقة', ['delta' => ($value > 0 ? '+' : '').$value]),
        ];
    };
@endphp

@push('head')
    {{-- أنماط الإحصاءات خارج حزمة Vite: تُنشر مع الوحدة بلا انتظار بناء أصول. --}}
    <link rel='stylesheet' href='{{ asset('css/insights.css') }}?v={{ @filemtime(public_path('css/insights.css')) ?: 1 }}'>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>إحصاءات الزوّار</h1>
            <p class="muted">
                {{ $from->translatedFormat('j F Y') }} — {{ $to->translatedFormat('j F Y') }} ({{ $days }} يومًا).
                القياس داخلي بالكامل: مصدره طلبات هذا الخادم ونبض متصفح الزائر، بلا أي خدمة خارجية.
            </p>
        </div>

        <div class="insights-range">
            @foreach ($ranges as $value => $label)
                <a href="{{ route('admin.insights', ['days' => $value]) }}" @class(['is-active' => $days === $value])>{{ $label }}</a>
            @endforeach
        </div>
    </header>

    @include('admin.insights.partials.nav')

    {{--
        الأرقام الستة التي تُقرأ أولًا.

        كل واحد منها يحمل أساسه تحته لا مجرّدًا (§١٣): «٤٢٪ ارتداد» بلا
        مقامها رقمٌ لا يمكن الحكم عليه، و«٤٢٪ من ١٩ جلسة» يقول للقارئ
        فورًا أن المدة أقصر من أن تُبنى عليها قرارات.
    --}}
    <section class="layout-metrics" aria-label="الإجماليات">
        <article class="stat">
            <span class="stat__value">{{ number_format($totals['visitors']) }}</span>
            <span class="stat__label">زائر فريد</span>
            <span class="{{ 'delta '.$delta($comparison['visitors']['delta_percent'])['class'] }}">{{ $delta($comparison['visitors']['delta_percent'])['text'] }}</span>
        </article>

        <article class="stat">
            <span class="stat__value">{{ number_format($totals['sessions']) }}</span>
            <span class="stat__label">زيارة</span>
            <span class="{{ 'delta '.$delta($comparison['sessions']['delta_percent'])['class'] }}">{{ $delta($comparison['sessions']['delta_percent'])['text'] }}</span>
        </article>

        <article class="stat">
            <span class="stat__value">{{ number_format($totals['page_views']) }}</span>
            <span class="stat__label">مشاهدة صفحة</span>
            <span class="muted">{{ $totals['pages_per_session'] }} صفحة لكل زيارة</span>
        </article>

        <article class="stat">
            <span class="stat__value">{{ $seconds($totals["avg_seconds"]) }}</span>
            <span class="stat__label">متوسط البقاء</span>
            <span class="muted">من {{ number_format($totals['measured_sessions']) }} زيارة لها زمن مقيس</span>
        </article>

        <article class="stat">
            <span class="stat__value">{{ $totals['bounce_rate'] }}٪</span>
            <span class="stat__label">ارتداد</span>
            <span class="muted">{{ number_format($totals['bounces']) }} من {{ number_format($totals['sessions']) }}</span>
        </article>

        <article class="stat">
            <span class="stat__value"><span class="live-dot" aria-hidden="true"></span>{{ $totals['live_now'] }}</span>
            <span class="stat__label">على الموقع الآن</span>
            <span class="muted">نشاط خلال آخر 5 دقائق</span>
        </article>
    </section>

    {{--
        تعريفات الأرقام في الصفحة لا في وثيقة جانبية.

        «مدة البقاء» و«الارتداد» يختلف تعريفهما بين أداة وأخرى، ومن يقارن
        رقمنا برقم أداة ثانية بلا معرفة التعريفين يقارن شيئين مختلفين.
    --}}
    <details class="alert">
        <summary>كيف تُحسب هذه الأرقام بالضبط؟</summary>
        <ul class="layout-flow">
            <li><strong>الزمن النشط:</strong> يُعدّ بالثانية ويتوقّف عند إخفاء التبويب أو خمول الزائر {{ (int) config('insights.idle_after_seconds', 60) }} ثانية. تبويب مفتوح ومنسيّ لا يُحتسب قراءةً.</li>
            <li><strong>الزيارة:</strong> نشاط متصل ينتهي بسكون {{ (int) config('insights.session_timeout_minutes', 30) }} دقيقة، فيبدأ الطلب التالي زيارة جديدة.</li>
            <li><strong>الارتداد:</strong> صفحة واحدة، بلا أي حدث تفاعل، وبزمن نشط أقل من {{ (int) config('insights.bounce_max_seconds', 5) }} ثوان. من قرأ مقالًا كاملًا في صفحة واحدة ليس مرتدًّا.</li>
            <li><strong>الزائر الفريد:</strong> كوكي طرف أول يعيش سنة. مسحُه أو تغييرُ الجهاز يجعل الشخص نفسه زائرَين — وهذا حدّ القياس بلا استثناء.</li>
            <li><strong>البلد:</strong> <span class="badge badge--assumption">فرضية</span> مستنتجة من المنطقة الزمنية أو لغة المتصفح، لا من قاعدة عناوين. تُصيب غالبًا وتخطئ مع VPN.</li>
            <li><strong>المستبعَد من كل ما سبق:</strong> زحف البوتات ({{ number_format($totals['bot_hits']) }} زيارة آلية في المدة){{ config('insights.count_staff') ? '' : ' وزيارات حسابات الإدارة' }}.</li>
        </ul>
    </details>

    <section aria-labelledby="trend-heading">
        <h2 id="trend-heading" class="section-title">الاتجاه اليومي</h2>
        @include('admin.insights.partials.chart', ['timeline' => $timeline, 'metric' => 'sessions', 'label' => __('الزيارات')])
    </section>

    {{-- ------------------------------------------------------------------
         من أين جاؤوا
         ------------------------------------------------------------------ --}}
    <section aria-labelledby="channels-heading">
        <h2 id="channels-heading" class="section-title">من أين جاؤوا</h2>
        <p class="muted">
            القناة مصنَّفة من وسوم الحملة أولًا ثم من المُحيل. «مباشر» تعني بلا مُحيل معلن —
            وتشمل النقر من تطبيقات الجوّال وملفات PDF، لا كتابة العنوان يدويًّا فقط.
        </p>

        @include('admin.insights.partials.breakdown', [
            'rows' => $channels,
            'heading' => __('القناة'),
            'empty' => __('لا زيارات مسجّلة في هذه المدة.'),
        ])
    </section>

    <div class="layout-main-aside">
        <div class="layout-flow">
            <section aria-labelledby="platforms-heading">
                <h2 id="platforms-heading" class="section-title">المنصات</h2>
                <p class="muted">أي منصة بعينها أرسلت الزائر — بما فيها مساعدات الذكاء، وهي القناة التي تقيس ظهورك في الإجابات سلوكيًّا.</p>

                @include('admin.insights.partials.breakdown', [
                    'rows' => $platforms,
                    'heading' => __('المنصة'),
                    'empty' => __('لا منصة محدَّدة في هذه المدة.'),
                ])
            </section>

            <section aria-labelledby="referrers-heading">
                <h2 id="referrers-heading" class="section-title">المواقع المُحيلة</h2>
                @include('admin.insights.partials.breakdown', [
                    'rows' => $referrers,
                    'heading' => __('النطاق'),
                    'empty' => __('لا إحالات من مواقع أخرى في هذه المدة.'),
                ])
            </section>

            <section aria-labelledby="campaigns-heading">
                <h2 id="campaigns-heading" class="section-title">الحملات الموسومة</h2>
                <p class="muted">تظهر هنا الروابط التي حملت <code>utm_campaign</code> فقط. الرابط بلا وسم يذوب في قناته ولا يمكن نسبه لاحقًا.</p>

                @include('admin.insights.partials.breakdown', [
                    'rows' => $campaigns,
                    'heading' => __('الحملة'),
                    'empty' => __('لا حملة موسومة في هذه المدة.'),
                ])
            </section>
        </div>

        <aside class="layout-aside layout-flow" aria-label="أنماط زمنية">
            <section aria-labelledby="rhythm-heading">
                <h2 id="rhythm-heading" class="section-title">ساعات النشاط</h2>
                @php($peakHours = max(1, max($rhythm['hours'])))
                <div class="hour-map" role="img" aria-label="توزيع الزيارات على ساعات اليوم">
                    @foreach ($rhythm['hours'] as $hour => $count)
                        <span class="hour-map__cell"
                              style="background: color-mix(in srgb, var(--brand-blue) {{ (int) round($count / $peakHours * 100) }}%, var(--surface-soft))"
                              title="{{ __('الساعة :hour — :count زيارة', ['hour' => $hour.':00', 'count' => $count]) }}"></span>
                    @endforeach
                </div>
                <div class="hour-map__legend"><span>00</span><span>12</span><span>23</span></div>

                @if ($rhythm['peak_hour'] !== null)
                    <p class="muted">الذروة الساعة {{ $rhythm['peak_hour'] }}:00 بتوقيت الخادم.</p>
                @endif
            </section>

            <section aria-labelledby="depth-heading">
                <h2 id="depth-heading" class="section-title">عمق الزيارة</h2>
                @php($depthMax = max(1, max($depth)))
                <ul class="hbar-list">
                    @foreach ($depth as $bucket => $count)
                        <li>
                            <span class="hbar-list__label">{{ $bucket }} صفحة</span>
                            <span class="hbar-list__track"><span class="hbar-list__fill" style="inline-size: {{ (int) round($count / $depthMax * 100) }}%; background: var(--brand-blue)"></span></span>
                            <span class="hbar-list__count">{{ $count }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section aria-labelledby="durations-heading">
                <h2 id="durations-heading" class="section-title">توزيع مدة البقاء</h2>
                <p class="muted">المتوسط وحده يخفي الشكل: موقعان بنفس المتوسط قد يحتاجان قرارين متعاكسين.</p>
                @php($durationMax = max(1, max($durations)))
                <ul class="hbar-list">
                    @foreach ($durations as $bucket => $count)
                        <li>
                            <span class="hbar-list__label">{{ $bucket }}</span>
                            <span class="hbar-list__track"><span class="hbar-list__fill" style="inline-size: {{ (int) round($count / $durationMax * 100) }}%; background: var(--brand-orange, #f59e0b)"></span></span>
                            <span class="hbar-list__count">{{ $count }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </aside>
    </div>

    {{-- ------------------------------------------------------------------
         ماذا زاروا
         ------------------------------------------------------------------ --}}
    <section aria-labelledby="pages-heading">
        <h2 id="pages-heading" class="section-title">الصفحات</h2>
        <p class="muted">عدد الزيارات وحده لا يقول إن الصفحة تعمل. اقرأه مع متوسط البقاء وعمق التمرير: زيارات كثيرة بزمن قصير وتمرير ضحل تعني صفحة تجذب ولا تُقنع.</p>

        <div class="table-wrap">
            <table class="table" data-table="matrix">
                <thead>
                    <tr>
                        <th>المسار</th>
                        <th>المشاهدات</th>
                        <th>الزوّار</th>
                        <th>متوسط البقاء</th>
                        <th>عمق التمرير</th>
                        <th>دخول</th>
                        <th>خروج</th>
                        <th>زمن الاستجابة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pages as $page)
                        <tr>
                            <td><span class="path-cell" title="{{ $page['path'] }}">{{ $page['path'] }}</span></td>
                            <td>{{ number_format($page['views']) }}</td>
                            <td>{{ number_format($page['visitors']) }}</td>
                            <td>
                                {{ $seconds($page["avg_seconds"]) }}
                                <span class="muted">من {{ $page['measured'] }}</span>
                            </td>
                            <td>{{ $page['avg_scroll'] }}٪</td>
                            <td>{{ $page['entries'] }}</td>
                            <td>{{ $page['exits'] }} <span class="muted">({{ $page['exit_rate'] }}٪)</span></td>
                            <td>{{ $page['avg_response_ms'] }}ms</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">لا مشاهدات مسجّلة في هذه المدة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="layout-main-aside">
        <div class="layout-flow">
            <section aria-labelledby="entry-heading">
                <h2 id="entry-heading" class="section-title">صفحات الدخول</h2>
                <p class="muted">أول ما رآه الزائر. هنا يُحكم على الانطباع الأول، وارتفاع الارتداد في صف واحد هنا أهمّ من أي متوسط عام.</p>

                <div class="table-wrap">
                    <table class="table" data-table="matrix">
                        <thead><tr><th>المسار</th><th>الزيارات</th><th>الارتداد</th><th>التحويلات</th></tr></thead>
                        <tbody>
                            @forelse ($entry_pages as $page)
                                <tr>
                                    <td><span class="path-cell" title="{{ $page['path'] }}">{{ $page['path'] }}</span></td>
                                    <td>{{ number_format($page['sessions']) }}</td>
                                    <td>{{ $page['bounce_rate'] }}٪ <span class="muted">({{ $page['bounces'] }})</span></td>
                                    <td>{{ $page['conversions'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="muted">لا بيانات.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section aria-labelledby="events-heading">
                <h2 id="events-heading" class="section-title">ما فعلوه</h2>
                <p class="muted">التنزيلات والنقرات الخارجية وإرسال النماذج تُلتقط تلقائيًّا بلا كود لكل زر. والحدث الموسوم «تحويل» يُحتسب في معدّل التحويل.</p>

                <div class="table-wrap">
                    <table class="table" data-table="matrix">
                        <thead><tr><th>الحدث</th><th>المرات</th><th>الزيارات</th><th>النوع</th></tr></thead>
                        <tbody>
                            @forelse ($events as $event)
                                <tr>
                                    <td>{{ $event['label'] }}</td>
                                    <td>{{ number_format($event['hits']) }}</td>
                                    <td>{{ number_format($event['sessions']) }}</td>
                                    <td>{{ $event['is_conversion'] ? 'تحويل' : $event['category'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="muted">لا أحداث مسجّلة. تحقّق من أن أصول الواجهة مبنية (npm run build).</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="layout-aside layout-flow" aria-label="خروج وأعطال">
            <section aria-labelledby="exit-heading">
                <h2 id="exit-heading" class="section-title">صفحات الخروج</h2>
                <div class="table-wrap">
                    <table class="table" data-table="matrix">
                        <thead><tr><th>المسار</th><th>مرات الخروج</th></tr></thead>
                        <tbody>
                            @forelse ($exit_pages as $page)
                                <tr>
                                    <td><span class="path-cell" title="{{ $page['path'] }}">{{ $page['path'] }}</span></td>
                                    <td>{{ number_format($page['exits']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="muted">لا بيانات.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- الروابط المكسورة: لا تظهر في أي تقرير آخر، وكل صف إصلاحٌ بدقيقة. --}}
            <section aria-labelledby="broken-heading">
                <h2 id="broken-heading" class="section-title">مسارات مكسورة</h2>
                @if ($broken === [])
                    <p class="muted">لا زائر وصل إلى صفحة مفقودة في هذه المدة.</p>
                @else
                    <div class="table-wrap">
                        <table class="table" data-table="matrix">
                            <thead><tr><th>المسار</th><th>الرمز</th><th>المرات</th></tr></thead>
                            <tbody>
                                @foreach ($broken as $row)
                                    <tr>
                                        <td><span class="path-cell" title="{{ $row['path'] }}">{{ $row['path'] }}</span></td>
                                        <td>{{ $row['status'] }}</td>
                                        <td>{{ $row['hits'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </aside>
    </div>

    {{-- ------------------------------------------------------------------
         بماذا تصفّحوا ومن أين
         ------------------------------------------------------------------ --}}
    <section aria-labelledby="tech-heading">
        <h2 id="tech-heading" class="section-title">الأجهزة والمتصفحات</h2>
        @include('admin.insights.partials.breakdown', [
            'rows' => $devices,
            'heading' => __('الجهاز'),
            'empty' => __('لا بيانات.'),
        ])
    </section>

    <div class="layout-main-aside">
        <div class="layout-flow">
            <section aria-labelledby="browsers-heading">
                <h2 id="browsers-heading" class="section-title">المتصفحات</h2>
                @include('admin.insights.partials.breakdown', ['rows' => $browsers, 'heading' => __('المتصفح'), 'empty' => __('لا بيانات.')])
            </section>

            <section aria-labelledby="countries-heading">
                <h2 id="countries-heading" class="section-title">
                    البلدان <span class="badge badge--assumption">فرضية</span>
                </h2>
                <p class="muted">
                    مستنتجة من المنطقة الزمنية ({{ number_format($coverage['timezone']) }} زيارة) أو من لغة المتصفح ({{ number_format($coverage['language']) }} زيارة).
                    التغطية {{ $coverage['coverage_percent'] }}٪ من {{ number_format($coverage['total']) }} زيارة —
                    و{{ number_format($coverage['unknown']) }} زيارة بلا أي إشارة موقع، ولا تُقدَّر بالتخمين.
                </p>

                @include('admin.insights.partials.breakdown', ['rows' => $countries, 'heading' => __('البلد'), 'empty' => __('لا بيانات.')])
            </section>
        </div>

        <aside class="layout-aside layout-flow" aria-label="النظام واللغة">
            <section aria-labelledby="os-heading">
                <h2 id="os-heading" class="section-title">أنظمة التشغيل</h2>
                <div class="table-wrap">
                    <table class="table" data-table="matrix">
                        <thead><tr><th>النظام</th><th>الجلسات</th><th>الحصة</th></tr></thead>
                        <tbody>
                            @forelse ($systems as $row)
                                <tr><td>{{ $row['label'] }}</td><td>{{ $row['sessions'] }}</td><td>{{ $row['share_percent'] }}٪</td></tr>
                            @empty
                                <tr><td colspan="3" class="muted">لا بيانات.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section aria-labelledby="lang-heading">
                <h2 id="lang-heading" class="section-title">لغات المتصفح</h2>
                <div class="table-wrap">
                    <table class="table" data-table="matrix">
                        <thead><tr><th>اللغة</th><th>الجلسات</th><th>الحصة</th></tr></thead>
                        <tbody>
                            @forelse ($languages as $row)
                                <tr><td>{{ $row['label'] }}</td><td>{{ $row['sessions'] }}</td><td>{{ $row['share_percent'] }}٪</td></tr>
                            @empty
                                <tr><td colspan="3" class="muted">لا بيانات.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </aside>
    </div>

    {{--
        زحف الآلات: مستبعَد من كل رقم أعلاه، ومعروض وحده.

        ليس ضوضاء تُحذف — هو المصدر الوحيد المتاح داخليًّا للسؤال «هل أنا
        مرئي لنماذج الذكاء أصلًا». بوت لم يزر موقعك لن يستشهد به نموذج.
    --}}
    <section aria-labelledby="crawlers-heading">
        <h2 id="crawlers-heading" class="section-title">زحف الآلات ونماذج الذكاء</h2>
        <p class="muted">مستبعَد من كل الأرقام أعلاه. يُعرض هنا لأنه يجيب سؤالًا لا يجيبه غيره: أي نموذج يقرأ موقعك، ومتى، وكم صفحة.</p>

        <div class="table-wrap">
            <table class="table" data-table="matrix">
                <thead><tr><th>الزاحف</th><th>الجهة</th><th>الزيارات</th><th>الصفحات</th><th>آخر زيارة</th></tr></thead>
                <tbody>
                    @forelse ($crawlers as $bot)
                        <tr>
                            <td>{{ $bot['name'] }}</td>
                            <td>{{ $bot['owner'] ?? '—' }}</td>
                            <td>{{ number_format($bot['visits']) }}</td>
                            <td>{{ number_format($bot['pages']) }}</td>
                            <td>{{ $bot['last_seen'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">لا زحف آلي مسجّل في هذه المدة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
