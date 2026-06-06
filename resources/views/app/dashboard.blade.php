@extends('layouts.app', ['title' => 'لوحة العمل', 'pageTitle' => 'لوحة العمل', 'pageKicker' => 'Dashboard'])

@php
    $metrics = $dashboard['metrics'];
    $nextStep = $dashboard['nextStep'];
    $currentProject = $dashboard['currentProject'];
    $toolPipeline = $dashboard['toolPipeline'];
    $briefAssessment = $dashboard['briefAssessment'] ?? ['completeness_score' => 0, 'next_actions' => []];
    $greeting = now()->hour < 12 ? 'صباح الخير' : (now()->hour < 17 ? 'مرحباً' : 'مساء الخير');

    // حساب تقدم الرحلة الكلية
    $totalTools = collect($toolPipeline)->sum('total');
    $completedTools = collect($toolPipeline)->sum('completed');
    $journeyPct = $totalTools > 0 ? (int) round(($completedTools / $totalTools) * 100) : 0;
    $currentStageData = collect($toolPipeline)->first(fn($s) => $s['remaining'] > 0) ?? collect($toolPipeline)->last();
@endphp

@section('content')

{{-- ── Journey Progress Bar (دائم في الأعلى) ─────────────────── --}}
<div class="dash-journey-bar">
    <div class="dash-journey-bar-inner">
        <div class="dash-journey-bar-text">
            <span class="dash-journey-bar-label">
                @if($currentProject)
                    رحلة <strong>{{ $currentProject->name }}</strong>
                @else
                    رحلتك التسويقية
                @endif
            </span>
            <span class="dash-journey-bar-stage">
                @if($currentStageData)
                    {{ $currentStageData['label'] }}
                    @if($currentStageData['remaining'] > 0)
                        · {{ $currentStageData['remaining'] }} {{ $currentStageData['remaining'] === 1 ? 'أداة متبقية' : 'أدوات متبقية' }}
                    @endif
                @endif
            </span>
        </div>
        <div class="dash-journey-bar-track">
            <div class="dash-journey-bar-fill" style="width: {{ $journeyPct }}%"></div>
        </div>
        <div class="dash-journey-bar-pct">
            <strong>{{ $journeyPct }}%</strong>
            <span>{{ $completedTools }} من {{ $totalTools }} أداة</span>
        </div>
    </div>
</div>

{{-- ── Welcome Strip ──────────────────────────────────────────── --}}
<section class="dash-welcome">
    <div class="dash-welcome-text">
        <h2 class="dash-welcome-title">{{ $greeting }}، {{ auth()->user()->name }}</h2>
        <p class="dash-welcome-sub">{{ $workspace->name }} · {{ $workspace->account?->subscription?->plan?->name_ar ?? 'بدون خطة' }}</p>
    </div>
    <div class="dash-welcome-actions">
        <a href="{{ route('projects.create') }}" class="btn btn-secondary">مشروع جديد</a>
        <a href="{{ $nextStep['action_route'] }}" class="btn btn-primary">{{ $nextStep['action_label'] }}</a>
    </div>
</section>

{{-- ── Next Step Spotlight ─────────────────────────────────────── --}}
<section class="dash-spotlight">
    <div class="dash-spotlight-glow"></div>
    <div class="dash-spotlight-content">
        <div class="dash-spotlight-badge">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            الخطوة التالية الأهم
        </div>
        <h3 class="dash-spotlight-title">{{ $nextStep['title'] }}</h3>
        <p>{{ $nextStep['summary'] }}</p>
    </div>
    <a href="{{ $nextStep['action_route'] }}" class="btn btn-primary btn-lg dash-spotlight-cta">{{ $nextStep['action_label'] }}</a>
</section>

{{-- ── Tool Pipeline (مسار الأدوات المرئي) ─────────────────────── --}}
@if($currentProject && $totalTools > 0)
<section class="dash-pipeline card mb-6">
    <div class="dash-card-head">
        <h3 class="heading-sm">مسار أدوات <span class="text-gradient">{{ $currentProject->name }}</span></h3>
        <a href="{{ route('tools.index') }}" class="btn btn-ghost btn-sm">كل الأدوات</a>
    </div>
    <div class="dash-pipeline-stages">
        @foreach($toolPipeline as $stageData)
            @php
                $stagePct = $stageData['total'] > 0
                    ? (int) round(($stageData['completed'] / $stageData['total']) * 100)
                    : 0;
                $isCurrent = $stageData['remaining'] > 0 && collect($toolPipeline)->first(fn($s) => $s['remaining'] > 0)['stage'] === $stageData['stage'];
            @endphp
            <div class="dash-pipeline-stage {{ $isCurrent ? 'dash-pipeline-stage--current' : '' }} {{ $stageData['completed'] === $stageData['total'] && $stageData['total'] > 0 ? 'dash-pipeline-stage--done' : '' }}">
                <div class="dash-pipeline-stage-head">
                    <span class="dash-pipeline-stage-num">{{ $stageData['stage'] }}</span>
                    <div>
                        <strong class="dash-pipeline-stage-label">{{ $stageData['label'] }}</strong>
                        <span class="dash-pipeline-stage-count">{{ $stageData['completed'] }}/{{ $stageData['total'] }}</span>
                    </div>
                    @if($stageData['completed'] === $stageData['total'] && $stageData['total'] > 0)
                        <svg class="dash-pipeline-check" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    @endif
                </div>
                <div class="dash-pipeline-stage-bar">
                    <div class="dash-pipeline-stage-fill" style="width: {{ $stagePct }}%"></div>
                </div>
                @if($isCurrent)
                    <div class="dash-pipeline-tools">
                        @foreach(collect($stageData['tools'])->where('unlocked', true)->where('completed', false)->take(3) as $pTool)
                            <a href="{{ $pTool['route'] }}" class="dash-pipeline-tool-chip">
                                {{ $pTool['name'] }}
                                @if($pTool['runs_count'] > 0)
                                    <span class="dash-pipeline-tool-runs">{{ $pTool['runs_count'] }}</span>
                                @endif
                            </a>
                        @endforeach
                        @php $moreCount = collect($stageData['tools'])->where('unlocked', true)->where('completed', false)->count() - 3; @endphp
                        @if($moreCount > 0)
                            <a href="{{ route('tools.index') }}" class="dash-pipeline-tool-more">+{{ $moreCount }} أداة</a>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- ── Metrics ─────────────────────────────────────────────────── --}}
<section class="dash-metrics">
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ $metrics['projects'] }}</strong>
        <span class="dash-metric-label">مشروع</span>
        @if ($metrics['active_projects'] > 0)
            <small class="dash-metric-sub">{{ $metrics['active_projects'] }} نشط</small>
        @endif
    </article>
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ $metrics['clients'] }}</strong>
        <span class="dash-metric-label">عميل</span>
    </article>
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ $completedTools }}</strong>
        <span class="dash-metric-label">أداة مكتملة</span>
        <small class="dash-metric-sub">من {{ $totalTools }}</small>
    </article>
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ $metrics['ai_generations'] }}</strong>
        <span class="dash-metric-label">مخرج AI</span>
    </article>
    @if($metrics['pending_approvals'] > 0)
    <article class="dash-metric dash-metric--alert">
        <strong class="dash-metric-value">{{ $metrics['pending_approvals'] }}</strong>
        <span class="dash-metric-label">موافقة معلقة</span>
    </article>
    @endif
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ $metrics['members'] }}</strong>
        <span class="dash-metric-label">عضو</span>
    </article>
</section>

{{-- ── Main Grid ──────────────────────────────────────────────── --}}
<section class="dash-grid">

    {{-- Left: Activity Feed --}}
    <div class="dash-feed">
        <article class="card dash-card">
            <div class="dash-card-head">
                <h3 class="heading-sm">آخر النشاطات</h3>
            </div>
            <div class="dash-feed-list">
                @forelse ($dashboard['recentToolRuns'] as $run)
                    <div class="dash-feed-item">
                        <div class="dash-feed-dot dash-feed-dot--tool"></div>
                        <div class="dash-feed-body">
                            <strong>{{ $run->tool?->name ?? $run->tool_code }}</strong>
                            <span>{{ $run->project?->name ?? 'بدون مشروع' }} · {{ $run->completeness_score }}% جاهزية</span>
                        </div>
                        <time class="dash-feed-time">{{ $run->created_at?->diffForHumans() }}</time>
                    </div>
                @empty
                    <div class="dash-empty-state">
                        <p class="app-empty mb-3">لم تُشغّل أي أداة بعد.</p>
                        @if($nextStep)
                            <a href="{{ $nextStep['action_route'] }}" class="btn btn-primary btn-sm">ابدأ بـ {{ $nextStep['title'] }}</a>
                        @endif
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

        <article class="card dash-card">
            <div class="dash-card-head">
                <h3 class="heading-sm">المشاريع</h3>
                <a href="{{ route('projects.index') }}" class="btn btn-ghost btn-sm">عرض الكل</a>
            </div>
            <div class="dash-feed-list">
                @forelse ($dashboard['recentProjects'] as $project)
                    @php
                        $projectPipeline = collect($toolPipeline);
                        // Find next action for this project
                    @endphp
                    <a href="{{ route('projects.show', $project) }}" class="dash-feed-item dash-feed-item--link">
                        <div class="dash-feed-body">
                            <strong>{{ $project->name }}</strong>
                            <span>{{ \App\Support\Dashboard\StageCatalog::label((int) $project->stage) }} · {{ $project->client?->name ?? 'بدون عميل' }} · {{ $project->status }}</span>
                        </div>
                        <span class="dash-feed-arrow">←</span>
                    </a>
                @empty
                    <div class="dash-empty-state">
                        <p class="app-empty mb-3">لا توجد مشاريع بعد.</p>
                        <a href="{{ route('projects.create') }}" class="btn btn-primary btn-sm">إنشاء أول مشروع</a>
                    </div>
                @endforelse
            </div>
        </article>
    </div>

    {{-- Right: Shortcuts + Progress --}}
    <div class="dash-side">
        <article class="card dash-card">
            <div class="dash-card-head">
                <h3 class="heading-sm">ملف المشروع التسويقي</h3>
                @if($currentProject)
                    <a href="{{ route('projects.brief.edit', $currentProject) }}" class="btn btn-ghost btn-sm">تحديث الملف</a>
                @endif
            </div>
            <div class="app-list">
                <div class="app-list-item">
                    <div>
                        <strong>اكتمال الملف</strong>
                        <small>كلما ارتفع، تحسنت الأدوات والتقارير والاستوديو.</small>
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

        {{-- Quick Actions --}}
        <article class="card dash-card">
            <div class="dash-card-head">
                <h3 class="heading-sm">الأقسام الرئيسية</h3>
            </div>
            <div class="dash-shortcuts">
                @foreach ($dashboard['actionCenters'] as $center)
                    <a href="{{ $center['route'] }}" class="dash-shortcut">
                        <span class="dash-shortcut-value">{{ $center['value'] }}</span>
                        <span class="dash-shortcut-label">{{ $center['title'] }}</span>
                    </a>
                @endforeach
            </div>
        </article>

        {{-- Stage Progress --}}
        <article class="card dash-card">
            <div class="dash-card-head">
                <h3 class="heading-sm">تقدم المراحل</h3>
                <a href="{{ route('tools.index') }}" class="btn btn-ghost btn-sm">الأدوات</a>
            </div>
            <div class="dash-stages">
                @foreach ($toolPipeline as $stageData)
                    @php
                        $stagePct = $stageData['total'] > 0
                            ? (int) round(($stageData['completed'] / $stageData['total']) * 100)
                            : 0;
                    @endphp
                    <div class="dash-stage">
                        <div class="dash-stage-head">
                            <span class="dash-stage-name">{{ $stageData['label'] }}</span>
                            <span class="dash-stage-count">{{ $stageData['completed'] }}/{{ $stageData['total'] }}</span>
                        </div>
                        <div class="dash-stage-bar">
                            <div class="dash-stage-fill" style="width: {{ $stagePct }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

        {{-- Recent AI Generations --}}
        @if ($dashboard['recentGenerations']->isNotEmpty())
            <article class="card dash-card">
                <div class="dash-card-head">
                    <h3 class="heading-sm">مخرجات الاستوديو</h3>
                    <a href="{{ route('studio.index') }}" class="btn btn-ghost btn-sm">الاستوديو</a>
                </div>
                <div class="dash-feed-list">
                    @foreach ($dashboard['recentGenerations'] as $gen)
                        <a href="{{ route('studio.generations.show', $gen) }}" class="dash-feed-item dash-feed-item--link">
                            <div class="dash-feed-dot dash-feed-dot--ai"></div>
                            <div class="dash-feed-body">
                                <strong>{{ $gen->template?->name ?? 'مخرج عام' }}</strong>
                                <span>{{ $gen->status }} · {{ $gen->tokens_used }} tokens</span>
                            </div>
                            <time class="dash-feed-time">{{ $gen->created_at?->diffForHumans() }}</time>
                        </a>
                    @endforeach
                </div>
            </article>
        @endif

        {{-- Value Reminder (للمستخدمين الجدد) --}}
        @if($completedTools === 0)
        <article class="card dash-card dash-value-card">
            <div class="dash-value-card-head">
                <svg width="20" height="20" fill="none" stroke="var(--p)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <strong>ابدأ وستعرف الفرق</strong>
            </div>
            <p class="text-caption mb-4">كل أداة تُنجزها تُغذي التالية — لن تحتاج لإعادة إدخال معلوماتك في كل مرة.</p>
            <div class="dash-value-items">
                <div class="dash-value-item">✓ رسالة واضحة تُقنع العميل</div>
                <div class="dash-value-item">✓ جمهور محدد بدقة</div>
                <div class="dash-value-item">✓ خطوة تالية واضحة دائماً</div>
            </div>
            <a href="{{ $nextStep['action_route'] }}" class="btn btn-primary btn-sm mt-4">{{ $nextStep['action_label'] }}</a>
        </article>
        @endif

    </div>
</section>

@endsection
