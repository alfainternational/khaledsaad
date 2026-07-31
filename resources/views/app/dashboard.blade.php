@extends('layouts.app')
@section('layout', 'dashboard')

@section('title', 'مشاريعك')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">لوحة التحكم</p>
            <h1>ملخص مشاريعك وخطوتك التالية</h1>
        </div>
        <a href="{{ route('app.projects.create') }}" class="btn btn--primary" data-tour="ابدأ من هنا: أضف مشروعك مرة واحدة، وكل التشخيصات بعدها تبني عليه.">أضف مشروعًا</a>
    </header>

    <section class="layout-metrics" aria-label="الملخص الأساسي">
        <article class="stat">
            <span class="stat__value">{{ count($projects) }}</span>
            <span class="stat__label">مشروع</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $reports_count }}</span>
            <span class="stat__label">نتيجة جاهزة</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $open_tasks }}</span>
            <span class="stat__label">مهمة تنتظرك</span>
        </article>
    </section>

    <div class="layout-main-aside">
        <div class="layout-flow">
        @if (($unfinished ?? []) !== [])
        {{-- طريق العودة. وعدنا المستخدم بأنه يقدر يغلق الصفحة ويرجع،
             وهذا هو المكان الذي يرجع منه. --}}
        <section aria-labelledby="resume-heading" class="resume-section">
            <h2 id="resume-heading" class="section-title">أكمل ما بدأته</h2>

            <ul class="list">
                @foreach ($unfinished as $item)
                    <li class="list__item resume-item">
                        <div class="resume-item__body">
                            <strong>{{ $item['tool_title'] }}</strong>
                            <span class="muted">{{ $item['project_name'] }} · {{ $item['hint'] }}</span>

                            @if ($item['state'] === 'draft' && $item['percent'] > 0)
                                <div class="progress__bar progress__bar--slim">
                                    <span style="inline-size: {{ $item['percent'] }}%"></span>
                                </div>
                            @endif
                        </div>

                        <a href="{{ $item['url'] }}" class="btn btn--primary btn--sm">{{ $item['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
        @endif

        @if ($projects === [])
        <section class="empty">
            <h2>أضف مشروعك الأول</h2>
            <p>أدخل معلوماته الأساسية مرة واحدة، وسنستخدمها لتخصيص الأسئلة والتقارير من دون تكرار.</p>
            <a href="{{ route('app.projects.create') }}" class="btn btn--primary">أضف مشروعك الأول</a>
        </section>
        @else
        <section aria-labelledby="projects-heading">
            <h2 id="projects-heading" class="section-title" data-tour="هنا مشاريعك ودرجة كل واحد منها — الدرجة تتحدث كلما شخّصت.">مشاريعك</h2>
            <div class="card-grid">
                @foreach ($projects as $project)
                    <a class="card card--link" href="{{ route('app.projects.show', $project['slug']) }}">
                        <h3>{{ $project['name'] }}</h3>

                        @if ($project['maturity'])
                            {{--
                                درجة النضج تتصدّر لأنها تصف النشاط كله لا أداة
                                واحدة. ومعها أساسها دائمًا: «٤١ من محورين» غير
                                «٤١ من ثمانية» (§١٣).
                            --}}
                            <p class="score-chip">
                                {{ $project['maturity']['maturity_score'] }}/100 · نضج تسويقي
                            </p>
                            <p class="muted">
                                من {{ $project['maturity']['axes_active'] }} محاور مقيسة
                                من {{ $project['maturity']['axes_total'] }}
                                @if ($project['maturity']['is_assumption'])
                                    · <span class="tag tag--assumption">فرضية</span>
                                @endif
                            </p>
                        @elseif ($project['latest_score'] !== null)
                            <p class="score-chip">{{ $project['latest_score'] }}/100 · {{ $project['score_band'] }}</p>
                        @else
                            <p class="muted">لم يبدأ التشخيص بعد</p>
                        @endif

                        <p class="muted">{{ $project['industry'] ?? 'قطاع غير محدد' }}</p>
                    </a>
                @endforeach
            </div>
        </section>
        @endif
        </div>

        <aside class="layout-aside layout-flow" aria-label="الخطوة التالية">
            <section class="card consultation-entry" aria-labelledby="smart-consultation-heading">
                <p class="eyebrow">المستشار التسويقي الذكي</p>
                <h2 id="smart-consultation-heading">لا تعرف أي تشخيص تبدأ به؟</h2>
                <p>ابدأ باستشارة واحدة تفهم مشروعك، تسمح بأكثر من اختيار عندما ينطبق، ثم تحدد التحليلات والأولويات المناسبة.</p>
                <a href="{{ route('app.consultations.index') }}" class="btn btn--primary">ابدأ التشخيص الذكي الشامل</a>
            </section>
        </aside>
    </div>

    <section aria-labelledby="tools-heading">
        <h2 id="tools-heading" class="section-title" data-tour="اختر أول تشخيص من هنا — نحو 10 دقائق وتخرج بدرجة وفجوات واضحة.">تشخيصات مقترحة للبدء</h2>
        <div class="card-grid">
            @foreach ($suggested_tools as $tool)
                <a class="card card--link" href="{{ route('app.tools.show', $tool['key']) }}">
                    <p class="eyebrow">{{ $tool['category'] }}</p>
                    <h3>{{ $tool['title'] }}</h3>
                    <p class="muted">{{ $tool['promise'] ?: $tool['description'] }}</p>
                </a>
            @endforeach
        </div>
    </section>
@endsection
