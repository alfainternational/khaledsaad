@extends('layouts.app', ['title' => $tool->name ?: $tool->code, 'pageTitle' => $tool->name ?: $tool->code, 'pageKicker' => 'Tool'])

@php
    $modeAvailability = $modeAvailability ?? [];
    $requestedMode = old('mode', $latestRun?->mode ?? $recommendedMode ?? $experience['recommended_mode'] ?? 'guided');
    $initialMode = ($modeAvailability[$requestedMode]['available'] ?? false)
        ? $requestedMode
        : ($recommendedMode ?? $experience['recommended_mode'] ?? 'guided');
    $defaultProjectId = old('project_id', $latestRun?->project_id ?? $currentProject?->id);
    $isDiagnosisTool = $tool->code === 'diagnosis';
    $hasUpstream = !empty($upstreamContext ?? []);
    $hasFeedsInto = !empty($feedsInto ?? []);
@endphp

@section('content')
<x-tools.decision-rail
    :tool="$tool"
    :stage-label="$stageLabel"
    :blueprint="$blueprint"
    :project-mode-context="$projectModeContext"
    :current-project="$currentProject"
/>

@if ($projects->isNotEmpty())
    @php($analysisIntegrity = $latestAuditReport['analysis_integrity'] ?? [])

    {{-- Audit-in-progress banner — the audit dispatched at onboarding runs async; let the user know drafts will sharpen --}}
    @if (in_array($latestAudit?->status, ['queued', 'running'], true))
        <div class="tool-audit-progress mb-4" role="status" aria-live="polite"
            @if ($currentProject) data-audit-status-url="{{ route('projects.audit.status', $currentProject) }}" @endif>
            <span class="tool-audit-progress-spinner" aria-hidden="true"></span>
            <div>
                <strong>جارٍ تحليل موقعك الآن</strong>
                <p>ستتحسّن المقترحات والمسودّات تلقائياً عند اكتمال التحليل. تابع الآن دون انتظار — سنحدّث الصفحة فور جهوزية النتائج.</p>
            </div>
        </div>
    @endif

    {{-- Context & analysis cards — collapsed by default to keep the tool focused on the form --}}
    <details class="tool-context-rail mb-4">
        <summary class="onb-advanced-summary">
            <span>تفاصيل التحليل والسياق (اختياري)</span>
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </summary>
        <div class="tool-context-rail-body">

    <section class="card panel-modern mb-4" @if(empty($latestAuditReport)) hidden @endif>
        <div class="app-section-head">
            <div>
                <span class="text-caption text-caption-strong">Marketing Intelligence Snapshot</span>
                <h3 class="heading-sm">نتيجة موقعك</h3>
            </div>
            <span class="app-badge">{{ $latestAuditSummary['executive_score'] ?? ($latestAuditReport['executive_scores']['executive'] ?? '--') }}%</span>
        </div>
        <div class="app-list">
            <div class="app-list-item">
                <div>
                    <strong>{{ $analysisIntegrity['label'] ?? 'لا توجد قراءة موثقة بعد' }}</strong>
                    <small>{{ $analysisIntegrity['summary'] ?? 'شغّل التحليل من صفحة المشروع حتى تعمل الأداة على scorecards وتشخيص أحدث.' }}</small>
                </div>
            </div>
            @if (! empty($latestAuditReport['honest_diagnosis'][0]))
                <div class="app-list-item">
                    <div>
                        <strong>Honest diagnosis</strong>
                        <small>{{ $latestAuditReport['honest_diagnosis'][0] }}</small>
                    </div>
                </div>
            @endif
            @if (! empty($latestAuditReport['priority_actions']['quick_wins_7_days'][0]))
                <div class="app-list-item">
                    <div>
                        <strong>الأولوية السريعة</strong>
                        <small>{{ $latestAuditReport['priority_actions']['quick_wins_7_days'][0] }}</small>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <div class="tool-upstream-context mb-4" data-upstream-context-root @if (! $hasUpstream) hidden @endif>
        <div class="tool-upstream-header">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            <strong>من خطوات سابقة</strong>
            <small>تُستخدم لتحسين الاقتراح</small>
        </div>
        <div class="tool-upstream-items" data-upstream-context-items>
            @foreach ($upstreamContext as $upstream)
                <div class="tool-upstream-item">
                    <strong>{{ $upstream['headline'] }}</strong>
                    <p>{{ Str::limit($upstream['text'], 120) }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="tool-upstream-context mb-4" data-project-brief-root @if (empty($projectBriefAssessment)) hidden @endif>
        <div class="tool-upstream-header">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h10"/></svg>
            <strong>ملف المشروع التسويقي</strong>
            <small data-project-brief-score>{{ $projectBriefAssessment['completeness_score'] ?? 0 }}% جاهزية</small>
        </div>
        <div class="tool-upstream-items" data-project-brief-items>
            @foreach (array_slice($projectBriefAssessment['reports']['executive_brief'] ?? [], 0, 3) as $line)
                <div class="tool-upstream-item">
                    <strong>{{ $line }}</strong>
                </div>
            @endforeach
            @foreach (array_slice($projectBriefAssessment['next_actions'] ?? [], 0, 1) as $line)
                <div class="tool-upstream-item">
                    <p>{{ $line }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <section class="card panel-modern mb-4" data-tool-briefing-root @if (empty($toolBriefing)) hidden @endif>
        <div class="app-section-head">
            <h3 class="heading-sm">كيف تستفيد هذه الأداة من ملف المشروع؟</h3>
            <span class="app-badge" data-tool-briefing-score>{{ $toolBriefing['readiness_score'] ?? 0 }}%</span>
        </div>
        <p class="text-body mb-4" data-tool-briefing-text>{{ $toolBriefing['summary']['text'] ?? '' }}</p>

        <div class="app-list mb-4" data-tool-briefing-signals @if (empty($toolBriefing['signals'])) hidden @endif>
            @if (! empty($toolBriefing['signals']))
                @foreach ($toolBriefing['signals'] as $signal)
                    <div class="app-list-item">
                        <div>
                            <strong>{{ $signal['label'] }}</strong>
                            <small>{{ $signal['value'] }}</small>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        <div class="tool-upstream-item mb-4" data-tool-briefing-missing @if (empty($toolBriefing['missing_signals'])) hidden @endif>
            <strong>ما الذي ما زال ناقصاً؟</strong>
            <p data-tool-briefing-missing-text>{{ implode('، ', $toolBriefing['missing_signals'] ?? []) }}</p>
        </div>

        <div class="tool-upstream-item" data-tool-briefing-next-action @if (empty($toolBriefing['next_action']['reason'])) hidden @endif>
            <strong data-tool-briefing-headline>{{ $toolBriefing['summary']['headline'] ?? 'الخطوة التالية' }}</strong>
            <p data-tool-briefing-reason>{{ $toolBriefing['next_action']['reason'] ?? '' }}</p>
            <div class="app-inline-actions mt-3" data-tool-briefing-actions @if (empty(data_get($toolBriefing, 'next_action.cta_url'))) hidden @endif>
                <a
                    href="{{ data_get($toolBriefing, 'next_action.cta_url', '#') }}"
                    class="btn btn-secondary btn-sm"
                    data-tool-briefing-action-link
                >{{ data_get($toolBriefing, 'next_action.cta_label', '') }}</a>
            </div>
        </div>
    </section>

        </div>
    </details>
@endif

@if ($projects->isEmpty())
    <section class="card panel-modern">
        <p class="app-empty mb-4">أنشئ مشروعاً أولاً حتى تُحفظ نتيجة الأداة مع مشروعك.</p>
        <a href="{{ route('projects.create') }}" class="btn btn-primary btn-lg">إنشاء مشروع</a>
    </section>
@else
    <section
        class="tool-workbench mb-8"
        @if ($isDiagnosisTool)
            data-diagnosis-preview-root
        @else
            data-tool-preview-root
            data-tool-preview-name="{{ $tool->name ?: $tool->code }}"
            data-tool-preview-result="{{ $blueprint['result_label'] }}"
            data-tool-preview-intro="{{ $blueprint['intro'] }}"
        @endif
    >
        <article class="card panel-modern tool-form-panel">
            <form
                method="POST"
                action="{{ route('tools.run', $tool) }}"
                class="app-form-grid"
                data-tool-workspace-form
                data-tool-ajax-form
                data-tool-ajax-url="{{ route('api.tools.run', $tool) }}"
                data-tool-load-url="{{ route('api.tools.load', $tool) }}"
                data-tool-code="{{ $tool->code }}"
                data-tool-name="{{ $tool->name ?: $tool->code }}"
                data-analyze-url="{{ route('api.ai.analyze') }}"
                data-suggest-url="{{ route('api.ai.suggest') }}"
            >
                @csrf

                <label class="app-field">
                    <span>{{ $isDiagnosisTool ? 'المشروع الذي تريد تشخيصه' : 'المشروع' }}</span>
                    <select class="app-input" name="project_id">
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected((string) $defaultProjectId === (string) $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </label>

                <x-tools.mode-segment
                    :blueprint="$blueprint"
                    :mode-availability="$modeAvailability"
                    :initial-mode="$initialMode"
                    :is-diagnosis="$isDiagnosisTool"
                />

                <details class="tool-context-rail tool-coach-rail">
                    <summary class="onb-advanced-summary">
                        <span>مساعد المدخلات (اختياري)</span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                <section class="tool-input-coach" data-tool-input-coach>
                    <div class="tool-input-coach-header">
                        <div>
                            <strong data-tool-coach-title>{{ $formExperience['summary']['title'] ?? 'مساعد المدخلات' }}</strong>
                            <p data-tool-coach-text>{{ $formExperience['summary']['intro'] ?? \Illuminate\Support\Str::limit((string) ($blueprint['intro'] ?? ''), 240, '…') }}</p>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" data-tool-focus-next>وجّهني للحقل التالي</button>
                    </div>

                    <div class="tool-input-coach-stats">
                        <div class="tool-input-coach-stat">
                            <strong data-tool-coach-critical-count>0</strong>
                            <span>حقول فارغة</span>
                        </div>
                        <div class="tool-input-coach-stat">
                            <strong data-tool-coach-weak-count>0</strong>
                            <span>تحتاج توضيحاً</span>
                        </div>
                        <div class="tool-input-coach-stat">
                            <strong data-tool-coach-ready-score>0%</strong>
                            <span>الجاهزية</span>
                        </div>
                    </div>

                    <div class="tool-input-coach-next" data-tool-coach-next-wrap>
                        <span class="tool-input-coach-next-label">الخطوة التالية</span>
                        <strong data-tool-coach-next-label-text>{{ $formExperience['summary']['focus_label'] ?? 'ابدأ بأول حقل مهمّ' }}</strong>
                        <p data-tool-coach-next-text class="text-sm">سيظهر هنا ما يفضّل أن تكمله الآن.</p>
                    </div>

                    <ul class="tool-input-coach-points" data-tool-coach-points>
                        @foreach (($formExperience['summary']['bullets'] ?? []) as $point)
                            <li>{{ $point }}</li>
                        @endforeach
                    </ul>
                </section>
                </details>

                <div class="tool-mode-panels">
                    @foreach ($blueprint['modes'] as $modeKey => $mode)
                        <x-tools.field-stepper
                            :mode-key="$modeKey"
                            :mode="$mode"
                            :mode-experience="$formExperience['modes'][$modeKey] ?? []"
                            :initial-mode="$initialMode"
                            :latest-run="$latestRun"
                            :is-diagnosis="$isDiagnosisTool"
                        />
                    @endforeach
                </div>

                <details>
                    <summary class="text-caption text-caption-strong">ملاحظة إضافية (اختياري)</summary>
                    <label class="app-field mt-4">
                        <span>ملاحظة قصيرة</span>
                        <textarea
                            class="app-input"
                            name="brief"
                            rows="3"
                            placeholder="ما يغيّر النتيجة أو يوضّحها"
                            @if ($isDiagnosisTool)
                                data-diagnosis-input="brief"
                            @else
                                data-tool-preview-input="brief"
                                data-tool-preview-label="ملاحظة إضافية"
                            @endif
                        >{{ old('brief', $latestRun?->inputs_json['brief'] ?? '') }}</textarea>
                    </label>
                </details>

                <div class="app-form-actions">
                    <button type="submit" class="btn btn-primary btn-lg" data-tool-submit>{{ $uiCopy['submit_label'] ?? 'حفظ المخرج الآن' }}</button>
                </div>

                <div class="tool-form-status" data-tool-status hidden></div>
            </form>

            {{-- AI Insight (realtime on blur) --}}
            <div class="tool-ai-insight" data-tool-ai-insight hidden>
                <div class="tool-ai-insight-icon">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div class="tool-ai-insight-body" data-tool-ai-insight-text></div>
            </div>

            {{-- AI Section --}}
            <div class="tool-ai-section">
                <div class="tool-ai-actions">
                    <button type="button" class="btn btn-ai" data-tool-analyze>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        قيّم جودة مدخلاتي
                    </button>
                    <button type="button" class="btn btn-ai-outline" data-tool-suggest>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        اقترح لي إجابات
                    </button>
                </div>
                <p class="tool-ai-auto-hint" data-tool-auto-hint hidden>
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    تقييم أثناء الكتابة
                </p>

                {{-- Structured Analysis Panel --}}
                <div class="tool-analysis-card" data-tool-analysis-panel hidden>
                    <div class="tool-analysis-header" data-tool-analysis-header>
                        <div class="tool-analysis-score-ring">
                            <svg viewBox="0 0 48 48" width="48" height="48">
                                <circle cx="24" cy="24" r="20" fill="none" stroke="var(--border)" stroke-width="4"/>
                                <circle cx="24" cy="24" r="20" fill="none" stroke="var(--accent)" stroke-width="4"
                                    stroke-linecap="round"
                                    stroke-dasharray="125.66"
                                    stroke-dashoffset="125.66"
                                    data-analysis-ring
                                    style="transform: rotate(-90deg); transform-origin: center;"
                                />
                            </svg>
                            <span class="tool-analysis-score-num" data-analysis-score>0</span>
                        </div>
                        <div class="tool-analysis-verdict">
                            <span class="tool-ai-badge">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                تقييم جودة المدخلات
                            </span>
                            <p data-analysis-verdict></p>
                        </div>
                    </div>

                    <div class="tool-analysis-dimensions" data-analysis-dimensions hidden></div>

                    <div class="tool-analysis-body">
                        <div class="tool-analysis-columns">
                            <div class="tool-analysis-col tool-analysis-strengths" data-analysis-strengths hidden>
                                <h4>
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    نقاط قوة
                                </h4>
                                <ul></ul>
                            </div>
                            <div class="tool-analysis-col tool-analysis-gaps" data-analysis-gaps hidden>
                                <h4>
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    فجوات تحتاج معالجة
                                </h4>
                                <ul></ul>
                            </div>
                        </div>

                        <div class="tool-analysis-recs" data-analysis-recs hidden>
                            <h4>
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                توصيات عملية
                            </h4>
                            <ol></ol>
                        </div>

                        <div class="tool-analysis-strategic" data-analysis-strategic hidden>
                            <p></p>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <x-tools.result-panel :latest-run="$latestRun" :blueprint="$blueprint" :is-diagnosis="$isDiagnosisTool" />
    </section>

    {{-- Next Step Recommendation --}}
    @if ($hasFeedsInto)
        <div class="tool-next-steps" data-tool-next-steps>
            <h3 class="heading-sm mb-3">يمكنك بعدها</h3>
            <div class="tool-next-steps-grid">
                @foreach ($feedsInto as $nextTool)
                    <a href="{{ route('tools.show', $nextTool) }}" class="tool-next-step-card">
                        <strong>{{ $nextTool->name }}</strong>
                        @if ($nextTool->description)
                            <p>{{ \Illuminate\Support\Str::limit(strip_tags((string) $nextTool->description), 90, '…') }}</p>
                        @endif
                        <span class="tool-next-step-arrow">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
@endif
@endsection
