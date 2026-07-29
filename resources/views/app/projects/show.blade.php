@extends('layouts.app')
@section('layout', 'detail')

@section('title', $project['name'])

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">مشروع</p>
            <h1>{{ $project['name'] }}</h1>
            <p class="muted">{{ $project['industry'] ?? 'قطاع غير محدد' }}</p>
        </div>
        <div class="page-head__actions">
            <form method="POST" action="{{ route('app.consultations.start', $project['slug']) }}">
                @csrf
                <button type="submit" class="btn btn--primary">ابدأ تشخيص مشروعك</button>
            </form>
            @feature(\App\Support\Billing\FeatureKey::REPORTS_AGENCY)
                <a href="{{ route('app.projects.agency-reports.index', $project['slug']) }}" class="btn btn--ghost">موجز الوكالة</a>
            @endfeature
            <a href="{{ route('app.projects.tasks', $project['slug']) }}" class="btn btn--ghost">المهام ({{ $project['tasks']['open'] }})</a>
            <a href="{{ route('app.projects.edit', $project['slug']) }}" class="btn btn--ghost">تعديل الملف</a>
        </div>
    </header>

    <div class="layout-main-aside">
    <div class="layout-flow">
    {{-- محرك النمو: القدرات المستمرة فوق أدوات التشغيل الاعتيادية --}}
    <section aria-labelledby="growth-heading">
        <h2 id="growth-heading" class="section-title">فرص إضافية لتحسين المشروع</h2>
        <div class="card-grid">
            <article class="card card--link">
                <p class="eyebrow">قبل أن تنفق</p>
                <h3>مختبر الجمهور</h3>
                <p class="muted">اختبر رسالتك على جمهور اصطناعي مبني من بياناتك — درجة واعتراض لكل شخصية.</p>
                @feature(\App\Support\Billing\FeatureKey::AUDIENCE_LAB)
                    <a href="{{ route('app.audience.show', $project['slug']) }}" class="btn btn--ghost btn--sm">اختبر رسالتك</a>
                @else
                    <a href="{{ route('app.billing') }}" class="btn btn--ghost btn--sm">متاح في خطة أعلى</a>
                @endfeature
            </article>

            {{--
                القياس قبل الإصلاح: البطاقة تفحص موقعك فعليًّا وتقول أين الخلل،
                وحزمة GEO أدناه تُصلح ما تكشفه. عرضها بعدها يجعل المستخدم يبني
                حزمة لا يعرف إن كان يحتاجها.
            --}}
            <article class="card card--link">
                <p class="eyebrow">مقيس من موقعك</p>
                <h3>الجاهزية للذكاء الاصطناعي</h3>
                <p class="muted">نفحص موقعك كما تقرأه النماذج، ونقرأ سجل خادمك لنعرف أي بوت زارك فعلًا.</p>
                <a href="{{ route('app.readiness.show', $project['slug']) }}" class="btn btn--ghost btn--sm">افحص موقعي</a>
            </article>

            <article class="card card--link">
                <p class="eyebrow">عملاؤك يسألون ChatGPT</p>
                <h3>الظهور في محركات الذكاء</h3>
                <p class="muted">حزمة تجعل مشروعك قابلًا للقراءة والاقتباس من مساعدات الذكاء الاصطناعي.</p>
                @feature(\App\Support\Billing\FeatureKey::GROWTH_GEO)
                    <a href="{{ route('app.geo.show', $project['slug']) }}" class="btn btn--ghost btn--sm">ابنِ حزمتك</a>
                @else
                    <a href="{{ route('app.billing') }}" class="btn btn--ghost btn--sm">متاح في خطة أعلى</a>
                @endfeature
            </article>

            <article class="card card--link">
                <p class="eyebrow">كل اثنين</p>
                <h3>النبض الأسبوعي</h3>
                <p class="muted">ما تغيّر، ما تأخر، وخطوة الأسبوع — يصلك دون أن تطلب.</p>
                @feature(\App\Support\Billing\FeatureKey::GROWTH_PULSE)
                    <a href="{{ route('app.pulse.index') }}" class="btn btn--ghost btn--sm">افتح النبض</a>
                @else
                    <a href="{{ route('app.billing') }}" class="btn btn--ghost btn--sm">متاح في خطة أعلى</a>
                @endfeature
            </article>
        </div>
    </section>

    <section aria-labelledby="reports-heading">
        <h2 id="reports-heading" class="section-title">التقارير</h2>

        @if ($project['reports'] === [])
            <p class="muted">لا توجد تقارير لهذا المشروع بعد. ابدأ أحد التشخيصات لإنشاء التقرير الأول.</p>
        @else
            <ul class="list">
                @foreach ($project['reports'] as $report)
                    <li class="list__item">
                        <a href="{{ route('app.reports.show', $report['id']) }}">{{ $report['title'] }}</a>
                        <span class="score-chip">{{ $report['score'] }}/100</span>
                        <time class="muted">{{ \Illuminate\Support\Carbon::parse($report['created_at'])->translatedFormat('j F Y') }}</time>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="kpis-heading">
        <h2 id="kpis-heading" class="section-title">المؤشرات</h2>

        @if ($project['kpis'] === [])
            <p class="muted">
                المؤشر رقم واحد تتابعه لتعرف هل تتقدّم فعلًا: كم تبيع، كم عميلًا يأتيك، كم يكلفك.
                جديد على الفكرة؟ اختر نموذجًا يشرح المقصود، ثم أدخل رقمك الحالي وهدفك.
            </p>
        @else
            <ul class="list">
                @foreach ($project['kpis'] as $kpi)
                    <li class="list__item">
                        <strong>{{ $kpi['name'] }}</strong>
                        <span class="muted">{{ $kpi['latest'] ?? '—' }} {{ $kpi['unit'] }}</span>
                        @if ($kpi['attainment_percent'] !== null)
                            <span class="score-chip">{{ $kpi['attainment_percent'] }}% من الهدف</span>
                        @endif
                        <form method="POST" action="{{ route('app.kpis.record', $kpi['id']) }}" class="inline-form">
                            @csrf
                            <input type="number" step="any" name="value" required aria-label="قراءة جديدة لـ{{ $kpi['name'] }}">
                            <button type="submit" class="btn btn--ghost btn--sm">سجّل</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        @if (! empty($kpiTemplates))
            <div class="kpi-templates" data-kpi-templates>
                <p class="kpi-templates__lead">نماذج جاهزة — اختر واحدًا ليشرح لك المقصود ويملأ النموذج:</p>

                @foreach ($kpiTemplates as $group)
                    <div class="kpi-template-group">
                        <span class="eyebrow">{{ $group['group'] }}</span>
                        <div class="kpi-template-chips">
                            @foreach ($group['items'] as $item)
                                <button type="button" class="kpi-template"
                                    data-name="{{ $item['name'] }}"
                                    data-unit="{{ $item['unit'] }}"
                                    data-measures="{{ $item['measures'] }}"
                                    data-example="{{ $item['example'] }}">
                                    {{ $item['name'] }}
                                    <small>{{ $item['unit'] }}</small>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('app.kpis.store', $project['slug']) }}" class="kpi-add" data-kpi-form>
            @csrf

            <p class="kpi-add__explainer" data-kpi-explainer hidden></p>

            <div class="inline-form inline-form--wrap">
                <input type="text" name="name" placeholder="اسم المؤشر" required aria-label="اسم المؤشر" data-kpi-name>
                <input type="text" name="unit" placeholder="الوحدة (ريال، عميل، %)" aria-label="الوحدة" data-kpi-unit>
                <input type="number" step="any" name="baseline" placeholder="رقمك الآن" aria-label="خط الأساس" data-kpi-baseline>
                <input type="number" step="any" name="target" placeholder="هدفك" aria-label="الهدف">
                <button type="submit" class="btn btn--primary btn--sm">أضف المؤشر</button>
            </div>
            <span class="field__help">«رقمك الآن» خط البداية، و«هدفك» ما تريد الوصول إليه — ونحسب لك نسبة التقدم بينهما.</span>
        </form>
    </section>

    @push('scripts')
        <script>
            // اختيار نموذج مؤشر: يملأ النموذج ويشرح ما يقيسه، تحسينًا تدريجيًا.
            document.querySelectorAll('[data-kpi-templates] .kpi-template').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    var form = document.querySelector('[data-kpi-form]');
                    if (!form) return;

                    form.querySelector('[data-kpi-name]').value = chip.dataset.name || '';
                    form.querySelector('[data-kpi-unit]').value = chip.dataset.unit || '';

                    var explainer = form.querySelector('[data-kpi-explainer]');
                    if (explainer) {
                        explainer.textContent = chip.dataset.measures + ' — ' + chip.dataset.example;
                        explainer.hidden = false;
                    }

                    form.querySelector('[data-kpi-baseline]').focus();
                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            });
        </script>
    @endpush

    <section aria-labelledby="tools-heading">
        <h2 id="tools-heading" class="section-title">ابدأ تشخيصًا لهذا المشروع</h2>
        <div class="card-grid">
            @foreach ($tools as $tool)
                @php($state = $engagements[$tool['key']] ?? null)

                <article @class(['card', 'card--muted' => ! $tool['is_runnable'], 'card--active' => ($state['state'] ?? 'new') !== 'new'])>
                    <p class="eyebrow">{{ $tool['category'] }}</p>
                    <h3>{{ $tool['title'] }}</h3>
                    <p class="muted">{{ $tool['description'] }}</p>

                    @if (! $tool['is_runnable'])
                        <p class="badge">قريبًا</p>
                    @elseif ($state && $state['state'] !== 'new')
                        <p class="resume-hint">{{ $state['hint'] }}</p>

                        @if ($state['state'] === 'draft' && $state['percent'] > 0)
                            <div class="progress__bar progress__bar--slim">
                                <span style="inline-size: {{ $state['percent'] }}%"></span>
                            </div>
                        @endif

                        <div class="card__actions">
                            <a href="{{ $state['url'] }}" class="btn btn--primary btn--sm">{{ $state['label'] }}</a>

                            @if ($state['can_restart'])
                                <form method="POST" action="{{ route('app.runs.start', [$project['slug'], $tool['key']]) }}">
                                    @csrf
                                    <input type="hidden" name="fresh" value="1">
                                    <button type="submit" class="btn btn--ghost btn--sm">ابدأ من جديد</button>
                                </form>
                            @endif
                        </div>
                    @else
                        <form method="POST" action="{{ route('app.runs.start', [$project['slug'], $tool['key']]) }}">
                            @csrf
                            <button type="submit" class="btn btn--primary btn--sm">ابدأ</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
    </div>

    <aside class="layout-aside layout-flow" aria-label="ملخص المشروع">
        <article class="card card--score">
            @if ($project['latest_score'] !== null)
                <p class="eyebrow">درجة الجاهزية</p>
                <p class="score-big">{{ $project['latest_score'] }}<small>/100</small></p>
                <p class="score-chip">{{ $project['score_band'] }}</p>
                @if ($project['comparison'])
                    <p @class(['delta', 'delta--up' => $project['comparison']['direction'] === 'up', 'delta--down' => $project['comparison']['direction'] === 'down'])>
                        {{ $project['comparison']['label'] }}
                    </p>
                @endif
            @else
                <p class="eyebrow">درجة الجاهزية</p>
                <p class="muted">لم تُحتسب بعد. ابدأ تشخيص الجاهزية لعرض الدرجة والأولويات هنا.</p>
            @endif
        </article>

        <article class="card">
            <p class="eyebrow">التنفيذ</p>
            <ul class="kv">
                <li><span>مهام مفتوحة</span><strong>{{ $project['tasks']['open'] }}</strong></li>
                <li><span>مهام متأخرة</span><strong>{{ $project['tasks']['overdue'] }}</strong></li>
                <li><span>مهام منجزة</span><strong>{{ $project['tasks']['done'] }}</strong></li>
            </ul>
        </article>
    </aside>
    </div>

@endsection
