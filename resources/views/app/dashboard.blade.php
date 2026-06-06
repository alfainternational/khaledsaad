@extends('layouts.app', ['title' => 'لوحة العمل', 'pageTitle' => 'لوحة العمل', 'pageKicker' => ''])

@php
    $nextStep = $dashboard['nextStep'];
    $currentProject = $dashboard['currentProject'];
    $toolPipeline = $dashboard['toolPipeline'];
    $briefAssessment = $dashboard['briefAssessment'] ?? ['completeness_score' => 0, 'next_actions' => []];
    $greeting = now()->hour < 12 ? 'صباح الخير' : (now()->hour < 17 ? 'مرحباً' : 'مساء الخير');

    $totalTools = collect($toolPipeline)->sum('total');
    $completedTools = collect($toolPipeline)->sum('completed');
    $journeyPct = $totalTools > 0 ? (int) round(($completedTools / $totalTools) * 100) : 0;
    $currentStageNum = optional(collect($toolPipeline)->first(fn($s) => $s['remaining'] > 0))['stage'];
@endphp

@section('content')

@if($currentProject)

{{-- ═══ قمرة المشروع: رأس المشروع + ستِبر المراحل ═══ --}}
<section class="card cockpit-head">
    <div class="cockpit-project">
        <div>
            <h2>{{ $currentProject->name }}</h2>
            <p class="cockpit-project-meta">
                {{ \App\Support\Dashboard\StageCatalog::label((int) $currentProject->stage) }}
                · {{ $currentProject->client?->name ?? 'بدون عميل' }}
                · {{ $journeyPct }}% مكتمل
            </p>
        </div>
        <div class="cockpit-head-actions">
            <a href="{{ route('projects.index') }}" class="btn btn-ghost btn-sm">كل المشاريع</a>
            <a href="{{ route('projects.show', $currentProject) }}" class="btn btn-secondary btn-sm">تفاصيل المشروع</a>
        </div>
    </div>

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

{{-- ═══ الخطوة التالية ═══ --}}
<section class="card cockpit-next">
    <div>
        <span class="cockpit-next-badge">الخطوة التالية</span>
        <strong>{{ $nextStep['title'] }}</strong>
        <p>{{ $nextStep['summary'] }}</p>
    </div>
    <a href="{{ $nextStep['action_route'] }}" class="btn btn-primary">{{ $nextStep['action_label'] }}</a>
</section>

{{-- ═══ عمودان: نشاط المشروع / المخرجات والملف ═══ --}}
<section class="dash-grid">
    <div class="dash-feed">
        <article class="card dash-card">
            <div class="dash-card-head">
                <h3 class="heading-sm">آخر ما أنجزته في المشروع</h3>
            </div>
            <div class="dash-feed-list">
                @forelse ($dashboard['recentToolRuns'] as $run)
                    <div class="dash-feed-item">
                        <div class="dash-feed-dot dash-feed-dot--tool"></div>
                        <div class="dash-feed-body">
                            <strong>{{ $run->tool?->name ?? $run->tool_code }}</strong>
                            <span>{{ $run->project?->name ?? 'بدون مشروع' }} · {{ $run->completeness_score }}% مكتمل</span>
                        </div>
                        <time class="dash-feed-time">{{ $run->created_at?->diffForHumans() }}</time>
                    </div>
                @empty
                    <div class="dash-empty-state">
                        <p class="app-empty mb-3">لم تُشغّل أي أداة بعد في هذا المشروع.</p>
                        <a href="{{ $nextStep['action_route'] }}" class="btn btn-primary btn-sm">ابدأ بـ {{ $nextStep['title'] }}</a>
                    </div>
                @endforelse

                @foreach ($dashboard['recentApprovals'] as $approval)
                    <div class="dash-feed-item">
                        <div class="dash-feed-dot dash-feed-dot--approval"></div>
                        <div class="dash-feed-body">
                            <strong>طلب اعتماد · {{ $approval->item_type }}</strong>
                            <span>{{ $approval->project?->name ?? 'غير مرتبط' }} · {{ $approval->status }}</span>
                        </div>
                        <time class="dash-feed-time">{{ $approval->created_at?->diffForHumans() }}</time>
                    </div>
                @endforeach
            </div>
        </article>
    </div>

    <div class="dash-side">
        <article class="card dash-card">
            <div class="dash-card-head">
                <h3 class="heading-sm">ملف مشروعك</h3>
                <a href="{{ route('projects.brief.edit', $currentProject) }}" class="btn btn-ghost btn-sm">أكمل الملف</a>
            </div>
            <div class="app-list">
                <div class="app-list-item">
                    <div>
                        <strong>اكتمال الملف</strong>
                        <small>كل ما أكملت أكثر، صارت النتائج والمخرجات أدقّ على مقاسك.</small>
                    </div>
                    <span class="app-badge">{{ $briefAssessment['completeness_score'] ?? 0 }}%</span>
                </div>
                @foreach (array_slice($briefAssessment['next_actions'] ?? [], 0, 3) as $action)
                    <div class="app-list-item">
                        <div><small>{{ $action }}</small></div>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="card dash-card">
            <div class="dash-card-head">
                <h3 class="heading-sm">آخر مخرجات الاستوديو</h3>
                <a href="{{ route('studio.index') }}" class="btn btn-ghost btn-sm">الاستوديو</a>
            </div>
            <div class="dash-feed-list">
                @forelse ($dashboard['recentGenerations'] as $gen)
                    <a href="{{ route('studio.generations.show', $gen) }}" class="dash-feed-item dash-feed-item--link">
                        <div class="dash-feed-dot dash-feed-dot--ai"></div>
                        <div class="dash-feed-body">
                            <strong>{{ $gen->template?->name ?? 'مخرج عام' }}</strong>
                            <span>{{ $gen->status }} · {{ $gen->tokens_used }} وحدة</span>
                        </div>
                        <time class="dash-feed-time">{{ $gen->created_at?->diffForHumans() }}</time>
                    </a>
                @empty
                    <p class="app-empty">لا توجد مخرجات بعد. افتح الاستوديو بعد ظهور نتائج التحليل.</p>
                @endforelse
            </div>
        </article>
    </div>
</section>

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
