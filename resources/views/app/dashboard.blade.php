@extends('layouts.app')
@section('layout', 'dashboard')

@section('title', 'مشاريعك')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ __('اليوم') }}</p>
            <h1>{{ __('ما أهم شيء أفعله الآن لتحسين مشروعي؟') }}</h1>
        </div>
    </header>

    {{-- شريط مساند لا ثلاث بطاقات متصدّرة: هذه عدّادات سياق، والبؤرة
         تبقى لـ«أكمل ما بدأته» ومشاريعك وخطوتك التالية. --}}
    <section class="layout-metrics layout-metrics--rail" aria-label="الملخص الأساسي">
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
            <p>أدخل معلوماته الأساسية مرة واحدة، وتُستخدم لتخصيص الأسئلة والتقارير من دون تكرار.</p>
            <a href="{{ route('app.projects.create') }}" class="btn btn--primary">أضف مشروعك الأول</a>
        </section>
        @else
        <section aria-labelledby="projects-heading">
            <h2 data-tour="هنا مشاريعك ودرجة كل واحد منها — الدرجة تتحدث كلما شخّصت." id="projects-heading" class="section-title">مشاريعك</h2>
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

                        <p class="muted">{{ $project['sector_display'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>
        @endif
        </div>

        <aside class="layout-aside layout-flow" aria-label="الخطوة التالية">
            <section class="card consultation-entry" aria-labelledby="smart-consultation-heading" data-primary-action>
                <p class="eyebrow">{{ __('خطوتك الأهم الآن') }}</p>
                <h2 id="smart-consultation-heading">{{ $projects === [] ? __('أضف مشروعك الأول') : __('ساعدني أختار من أين أبدأ') }}</h2>
                <p>{{ $projects === []
                    ? __('تحتاج نحو 3 دقائق. بعدها نستخدم وصف المشروع لتخصيص التشخيصات والمهام دون تكرار.')
                    : __('تحتاج نحو 10 دقائق. سنفرز وضع مشروعك ونقترح التشخيص الأنسب بدل عرض كل الخيارات معًا.') }}</p>
                <a href="{{ $projects === [] ? route('app.projects.create') : route('app.consultations.index') }}" class="btn btn--primary">
                    {{ $projects === [] ? __('أضف مشروعك') : __('ابدأ اختيار نقطة البداية') }}
                </a>
            </section>
        </aside>
    </div>

    <section aria-labelledby="tools-heading">
        <h2 id="tools-heading" class="section-title">{{ __('التشخيصات الأخرى') }}</h2>
        <p class="muted">{{ __('عندما تعرف ما تحتاجه، ستجد كتالوج التشخيصات كاملًا في صفحة واحدة.') }}</p>
        <a class="btn btn--ghost" href="{{ route('app.tools.index') }}">{{ __('استكشف بقية التشخيصات') }}</a>
    </section>
@endsection
