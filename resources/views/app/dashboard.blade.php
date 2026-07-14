@extends('layouts.app', ['title' => 'لوحة العمل', 'pageTitle' => 'لوحة العمل', 'pageKicker' => ''])

@php
    $nextStep = $dashboard['nextStep'];
    $currentProject = $dashboard['currentProject'];
    $toolPipeline = $dashboard['toolPipeline'];
    $metrics = $dashboard['metrics'];
    $briefAssessment = $dashboard['briefAssessment'] ?? ['completeness_score' => 0, 'next_actions' => []];
    $greeting = now()->hour < 12 ? 'صباح الخير' : (now()->hour < 17 ? 'مرحباً' : 'مساء الخير');

    $totalTools = collect($toolPipeline)->sum('total');
    $completedTools = collect($toolPipeline)->sum('completed');
    $journeyPct = $totalTools > 0 ? (int) round(($completedTools / $totalTools) * 100) : 0;
    $currentStageNum = optional(collect($toolPipeline)->first(fn($s) => $s['remaining'] > 0))['stage'];
@endphp

@section('content')

@if($currentProject)

<div class="ta-dash">

    {{-- ═══ تحية ═══ --}}
    <section class="ta-pagehead">
        <div>
            <h2>{{ $greeting }}، {{ auth()->user()->name }}</h2>
            <p>أنت داخل مشروع <strong>{{ $currentProject->name }}</strong> · {{ $journeyPct }}% من الرحلة مكتمل</p>
        </div>
        <div class="ta-pagehead-actions">
            <a href="{{ route('projects.index') }}" class="btn btn-ghost btn-sm">كل المشاريع</a>
            <a href="{{ route('projects.show', $currentProject) }}" class="btn btn-secondary btn-sm">تفاصيل المشروع</a>
        </div>
    </section>

    {{-- ═══ ستِبر المراحل ═══ --}}
    <section class="ta-panel cockpit-head">
        <div class="cockpit-stepper">
            @foreach($toolPipeline as $stageData)
                @php
                    $isDone = $stageData['total'] > 0 && $stageData['completed'] === $stageData['total'];
                    $isCurrent = $stageData['stage'] === $currentStageNum;
                @endphp
                <div class="cockpit-step {{ $isDone ? 'cockpit-step--done' : '' }} {{ $isCurrent ? 'cockpit-step--current' : '' }}">
                    <span class="cockpit-step-num">
                        @if($isDone)
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        @else
                            {{ $stageData['stage'] }}
                        @endif
                    </span>
                    <span class="cockpit-step-label">{{ $stageData['label'] }}</span>
                    <span class="cockpit-step-count">{{ $stageData['completed'] }}/{{ $stageData['total'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ═══ بطاقات المؤشرات ═══ --}}
    <section class="ta-metrics">
        <article class="ta-metric">
            <span class="ta-metric-icon ta-icon--indigo">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </span>
            <div class="ta-metric-foot">
                <div>
                    <span class="ta-metric-label">مشاريع نشطة</span>
                    <strong class="ta-metric-value">{{ number_format($metrics['active_projects']) }}</strong>
                </div>
            </div>
        </article>

        <article class="ta-metric">
            <span class="ta-metric-icon ta-icon--teal">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <div class="ta-metric-foot">
                <div>
                    <span class="ta-metric-label">أدوات مكتملة</span>
                    <strong class="ta-metric-value">{{ number_format($completedTools) }}</strong>
                </div>
            </div>
        </article>

        <article class="ta-metric">
            <span class="ta-metric-icon ta-icon--amber">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </span>
            <div class="ta-metric-foot">
                <div>
                    <span class="ta-metric-label">مخرجات الاستوديو</span>
                    <strong class="ta-metric-value">{{ number_format($metrics['ai_generations']) }}</strong>
                </div>
            </div>
        </article>

        <article class="ta-metric">
            <span class="ta-metric-icon ta-icon--rose">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </span>
            <div class="ta-metric-foot">
                <div>
                    <span class="ta-metric-label">بانتظار الاعتماد</span>
                    <strong class="ta-metric-value">{{ number_format($metrics['pending_approvals']) }}</strong>
                </div>
            </div>
        </article>
    </section>

    {{-- ═══ الخطوة التالية ═══ --}}
    <section class="ta-panel cockpit-next">
        <div>
            <span class="cockpit-next-badge">الخطوة التالية</span>
            <strong>{{ $nextStep['title'] }}</strong>
            <p>{{ $nextStep['summary'] }}</p>
        </div>
        <a href="{{ $nextStep['action_route'] }}" class="btn btn-primary">{{ $nextStep['action_label'] }}</a>
    </section>

    {{-- ═══ مخطط المراحل + عدّاد الرحلة ═══ --}}
    <section class="ta-cols">
        <article class="ta-panel">
            <div class="ta-panel-head">
                <div>
                    <div class="ta-panel-title">تقدّم المراحل</div>
                    <div class="ta-panel-sub">الأدوات المكتملة مقابل الإجمالي في كل مرحلة</div>
                </div>
                <a href="{{ route('tools.index') }}" class="btn btn-ghost btn-sm">الأدوات</a>
            </div>
            <div class="ta-chart ta-chart--pad" data-chart-key="stages"></div>
        </article>

        <article class="ta-panel ta-target">
            <div class="ta-panel-head">
                <div>
                    <div class="ta-panel-title">اكتمال الرحلة</div>
                    <div class="ta-panel-sub">نسبة إنجاز أدوات المشروع</div>
                </div>
            </div>
            <div class="ta-target-chart">
                <div class="ta-chart" data-chart-key="progress"></div>
            </div>
            <p class="ta-target-caption">
                أنجزتَ {{ number_format($completedTools) }} من {{ number_format($totalTools) }} أداة. كل خطوة تجعل مخرجاتك أدقّ.
            </p>
            <div class="ta-target-stats">
                <div class="ta-target-stat">
                    <span>مكتملة</span>
                    <strong>{{ number_format($completedTools) }}</strong>
                </div>
                <div class="ta-target-stat">
                    <span>الإجمالي</span>
                    <strong>{{ number_format($totalTools) }}</strong>
                </div>
                <div class="ta-target-stat">
                    <span>الملف</span>
                    <strong>{{ $briefAssessment['completeness_score'] ?? 0 }}%</strong>
                </div>
            </div>
        </article>
    </section>

    {{-- ═══ النشاط الشهري ═══ --}}
    <article class="ta-panel">
        <div class="ta-panel-head">
            <div>
                <div class="ta-panel-title">نشاطك الشهري</div>
                <div class="ta-panel-sub">عدد مرات تشغيل الأدوات خلال آخر ٨ أشهر</div>
            </div>
        </div>
        <div class="ta-chart ta-chart--pad" data-chart-key="activity"></div>
    </article>

    {{-- ═══ آخر النشاطات + ملف المشروع/الاستوديو ═══ --}}
    <section class="ta-cols-flip">
        <article class="ta-panel">
            <div class="ta-panel-head">
                <div>
                    <div class="ta-panel-title">آخر ما أنجزته في المشروع</div>
                    <div class="ta-panel-sub">أحدث تشغيلات الأدوات</div>
                </div>
            </div>
            <div class="ta-table-wrap">
                <table class="ta-table">
                    <thead>
                        <tr>
                            <th>الأداة</th>
                            <th>المشروع</th>
                            <th>الاكتمال</th>
                            <th>الوقت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dashboard['recentToolRuns'] as $run)
                            @php
                                $score = (int) ($run->completeness_score ?? 0);
                                $statusClass = $score >= 80 ? 'success' : ($score >= 40 ? 'warning' : 'danger');
                            @endphp
                            <tr>
                                <td>
                                    <div class="ta-cell-primary">
                                        <span class="ta-cell-avatar">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </span>
                                        <strong>{{ $run->tool?->name ?? $run->tool_code }}</strong>
                                    </div>
                                </td>
                                <td>{{ $run->project?->name ?? '—' }}</td>
                                <td><span class="ta-status ta-status--{{ $statusClass }}">{{ $score }}%</span></td>
                                <td class="ta-side-time">{{ $run->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="dash-empty-state">
                                        <p class="app-empty mb-3">لم تُشغّل أي أداة بعد في هذا المشروع.</p>
                                        <a href="{{ $nextStep['action_route'] }}" class="btn btn-primary btn-sm">ابدأ بـ {{ $nextStep['title'] }}</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <div class="ta-dash">
            <article class="ta-panel">
                <div class="ta-panel-head">
                    <div class="ta-panel-title">ملف مشروعك</div>
                    <a href="{{ route('projects.brief.edit', $currentProject) }}" class="btn btn-ghost btn-sm">أكمل الملف</a>
                </div>
                <div class="ta-side-list">
                    <div class="ta-side-item">
                        <span class="ta-side-dot ta-side-dot--teal"></span>
                        <div class="ta-side-body">
                            <strong>اكتمال الملف</strong>
                            <span>كل ما أكملت أكثر، صارت النتائج أدقّ على مقاسك.</span>
                        </div>
                        <span class="ta-status ta-status--{{ ($briefAssessment['completeness_score'] ?? 0) >= 60 ? 'success' : 'warning' }}">{{ $briefAssessment['completeness_score'] ?? 0 }}%</span>
                    </div>
                    @foreach (array_slice($briefAssessment['next_actions'] ?? [], 0, 3) as $action)
                        <div class="ta-side-item">
                            <span class="ta-side-dot ta-side-dot--gold"></span>
                            <div class="ta-side-body">
                                <span>{{ $action }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="ta-panel">
                <div class="ta-panel-head">
                    <div class="ta-panel-title">آخر مخرجات الاستوديو</div>
                    <a href="{{ route('studio.index') }}" class="btn btn-ghost btn-sm">الاستوديو</a>
                </div>
                <div class="ta-side-list">
                    @forelse ($dashboard['recentGenerations'] as $gen)
                        <a href="{{ route('studio.generations.show', $gen) }}" class="ta-side-item">
                            <span class="ta-side-dot ta-side-dot--p"></span>
                            <div class="ta-side-body">
                                <strong>{{ $gen->template?->name ?? 'مخرج عام' }}</strong>
                                <span>{{ $gen->status }} · {{ $gen->tokens_used }} وحدة</span>
                            </div>
                            <span class="ta-side-time">{{ $gen->created_at?->diffForHumans() }}</span>
                        </a>
                    @empty
                        <p class="app-empty">لا توجد مخرجات بعد. افتح الاستوديو بعد ظهور نتائج التحليل.</p>
                    @endforelse
                </div>
            </article>
        </div>
    </section>

</div>

<script type="application/json" id="dashboard-charts-payload">@json($charts)</script>

@else

{{-- ═══ لا يوجد مشروع بعد ═══ --}}
<section class="card dash-hero">
    <div class="dash-hero-top">
        <div>
            <h2 class="dash-hero-greeting">{{ $greeting }}، {{ auth()->user()->name }}</h2>
            <p class="dash-hero-sub">ابدأ بإنشاء أول مشروع لتظهر لك قمرة القيادة الخاصة به.</p>
        </div>
    </div>
    <div class="dash-hero-next">
        <div class="dash-hero-next-text">
            <span class="dash-hero-next-badge">الخطوة الأولى</span>
            <strong>أنشئ مشروعك الأول</strong>
            <p>كل مشروع له قمرة قيادة تجمع مراحله ونتائجه ومخرجاته في مكان واحد.</p>
        </div>
        <a href="{{ route('projects.create') }}" class="btn btn-primary dash-hero-next-cta">إنشاء مشروع</a>
    </div>
</section>

@endif

@endsection
