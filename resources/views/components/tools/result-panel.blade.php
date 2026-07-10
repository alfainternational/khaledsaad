@props([
    'latestRun' => null,
    'blueprint',
    'isDiagnosis' => false,
])

@php
    $isAiGenerated = $latestRun && ! empty($latestRun->summary_json['ai_generated']);
    $agencyVerdict = $latestRun?->summary_json['agency_verdict'] ?? null;
    $nextActions = $latestRun?->next_actions_json ?? [];
    $score = $latestRun?->completeness_score ?? 0;
    $circumference = 2 * 3.14159 * 42;
    $dashOffset = $circumference - ($circumference * $score / 100);
@endphp

<aside class="card panel-modern tool-preview-panel">
    <div class="tool-progress-ring-wrap" data-tool-ring>
        <svg class="tool-progress-ring" viewBox="0 0 100 100">
            <circle class="tool-progress-ring-bg" cx="50" cy="50" r="42" />
            <circle
                class="tool-progress-ring-fill"
                cx="50" cy="50" r="42"
                stroke-dasharray="{{ $circumference }}"
                stroke-dashoffset="{{ $dashOffset }}"
                data-tool-ring-circle
                data-circumference="{{ $circumference }}"
            />
        </svg>
        <div class="tool-progress-ring-label">
            <strong {{ $isDiagnosis ? 'data-diagnosis-score' : 'data-tool-preview-score' }}>{{ $score }}%</strong>
            <span>{{ $isDiagnosis ? 'وضوح الصورة' : 'الجاهزية' }}</span>
        </div>
    </div>

    <div class="tool-latest-result" data-tool-result-body>
        @if ($isAiGenerated)
            <span class="tool-ai-badge tool-ai-badge-sm">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                تحليل ذكي
            </span>
        @endif
        <strong {{ $isDiagnosis ? 'data-diagnosis-headline' : 'data-tool-preview-headline' }}>
            {{ $latestRun->summary_json['headline'] ?? ($isDiagnosis ? 'ابدأ بالإجابة لتظهر الخلاصة.' : $blueprint['result_label'].' أولية') }}
        </strong>
        <p class="tool-result-analysis" {{ $isDiagnosis ? 'data-diagnosis-text' : 'data-tool-preview-text' }}>
            {{ $latestRun->summary_json['text'] ?? ($isDiagnosis ? 'ستظهر قراءة مختصرة لحالة المشروع.' : $blueprint['intro']) }}
        </p>

        @if (! empty($agencyVerdict))
            <section class="agency-verdict-card" aria-label="حكم تشغيل الوكالة">
                <div class="agency-verdict-head">
                    <span>حكم تشغيل الوكالة</span>
                    <strong>{{ $agencyVerdict['score'] ?? 0 }}/100</strong>
                </div>

                <p class="agency-verdict-decision">{{ $agencyVerdict['decision'] ?? 'راجع القياس قبل القرار.' }}</p>

                <div class="agency-verdict-meta">
                    <div>
                        <span>مستوى المخاطرة</span>
                        <strong>{{ $agencyVerdict['risk_level'] ?? 'غير محدد' }}</strong>
                    </div>
                    <div>
                        <span>أول طلب</span>
                        <strong>{{ $agencyVerdict['demands'][0] ?? 'اطلب أرقاماً قابلة للقياس.' }}</strong>
                    </div>
                </div>

                @if (! empty($agencyVerdict['demands']))
                    <div class="agency-verdict-list">
                        <strong>مطالب من الوكالة</strong>
                        <ul>
                            @foreach (array_slice($agencyVerdict['demands'], 0, 3) as $demand)
                                <li>{{ $demand }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (! empty($agencyVerdict['questions']))
                    <div class="agency-verdict-list">
                        <strong>أسئلة الاجتماع القادم</strong>
                        <ul>
                            @foreach (array_slice($agencyVerdict['questions'], 0, 3) as $question)
                                <li>{{ $question }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endif

        @if ($isAiGenerated && !empty($latestRun?->summary_json['bullets']))
            <div class="tool-result-recs-label">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                توصيات عملية
            </div>
        @endif

        <ul class="tool-bullet-list {{ $isAiGenerated ? 'tool-bullet-recs' : '' }}" {{ $isDiagnosis ? 'data-diagnosis-bullets' : 'data-tool-preview-bullets' }}>
            @if (! empty($latestRun?->summary_json['bullets']))
                @foreach ($latestRun->summary_json['bullets'] as $bullet)
                    <li>{{ $bullet }}</li>
                @endforeach
            @else
                <li>{{ $isDiagnosis ? 'ستظهر الأولوية التالية بعد ملء المدخلات.' : 'املأ الحقول لتظهر التوصيات.' }}</li>
            @endif
        </ul>

        <section class="tool-next-actions-card" data-tool-next-actions-card @if (empty($nextActions)) hidden @endif>
            <strong>خطوات المتابعة</strong>
            <ol data-tool-next-actions-list>
                @foreach (array_slice($nextActions, 0, 4) as $action)
                    <li>{{ $action }}</li>
                @endforeach
            </ol>
        </section>

        @php $specialistReview = $latestRun?->output_json['specialist_review'] ?? null; @endphp
        @if (! empty($specialistReview['panels']))
            <div class="specialist-review" data-specialist-review>
                <div class="specialist-review-head">
                    <span class="specialist-review-title">مراجعة الأخصائيين</span>
                    @if (! is_null($specialistReview['score']))
                        <span class="specialist-review-score">{{ $specialistReview['score'] }}%</span>
                    @endif
                </div>
                @foreach ($specialistReview['panels'] as $panel)
                    @php $tier = $panel['score'] >= 80 ? 'good' : ($panel['score'] >= 50 ? 'warn' : 'low'); @endphp
                    <div class="specialist-panel">
                        <div class="specialist-panel-head">
                            <span class="specialist-panel-name">{{ $panel['name'] }}</span>
                            <span class="specialist-panel-score specialist-tier-{{ $tier }}">{{ $panel['score'] }}%</span>
                        </div>
                        @if (! empty($panel['items']))
                            <ul class="specialist-items">
                                @foreach (array_slice($panel['items'], 0, 3) as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if ($latestRun)
        <div class="{{ $isDiagnosis ? 'diagnosis-saved-note' : 'tool-preview-note' }}">
            <small>آخر حفظ: {{ $latestRun->created_at?->diffForHumans() }} · {{ $latestRun->completeness_score }}%</small>
        </div>
    @endif

    <div class="tool-draft-indicator" data-tool-draft-indicator hidden>
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span>مسودة محفوظة</span>
    </div>
</aside>
