@extends('layouts.app', ['title' => 'تقارير الذكاء التسويقي', 'pageTitle' => 'تقارير الذكاء التسويقي', 'pageKicker' => 'تقارير التحليل'])

@section('content')
<section class="app-stat-grid mb-8">
    <article class="card">
        <span class="app-stat-label">تشغيل الأدوات</span>
        <strong class="app-stat-value">{{ $toolRunsCount }}</strong>
    </article>
    <article class="card">
        <span class="app-stat-label">مخرجات الذكاء</span>
        <strong class="app-stat-value">{{ $aiGenerationsCount }}</strong>
    </article>
    <article class="card">
        <span class="app-stat-label">صحة ملفات المشاريع</span>
        <strong class="app-stat-value">{{ $briefHealthAverage }}%</strong>
    </article>
    <article class="card">
        <span class="app-stat-label">تشغيلات التدقيق</span>
        <strong class="app-stat-value">{{ $auditRunsCount }}</strong>
    </article>
    <article class="card">
        <span class="app-stat-label">متوسط الدرجة التنفيذية</span>
        <strong class="app-stat-value">{{ $averageExecutiveScore }}%</strong>
    </article>
    <article class="card">
        <span class="app-stat-label">مشاريع المراقبة</span>
        <strong class="app-stat-value">{{ $monitoredProjectsCount }}</strong>
    </article>
    <article class="card">
        <span class="app-stat-label">اعتمادات معلقة</span>
        <strong class="app-stat-value">{{ $pendingApprovalsCount }}</strong>
    </article>
    <article class="card">
        <span class="app-stat-label">اعتمادات معتمدة</span>
        <strong class="app-stat-value">{{ $approvedApprovalsCount }}</strong>
    </article>
</section>

<section class="app-grid app-two-col mb-8">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">آخر تشغيلات التدقيق</h3>
        </div>
        <div class="app-list">
            @forelse ($recentAuditRuns as $run)
                @php($scores = $run->report_json['executive_scores'] ?? [])
                <div class="app-list-item">
                    <div>
                        <strong>{{ $run->project?->name ?? 'بدون مشروع' }}</strong>
                        <small>
                            {{ $run->project?->client?->name ?? 'بدون عميل' }}
                            · {{ $run->trigger_source === 'monitoring' ? 'مراقبة' : 'تحليل' }}
                            · {{ $run->created_at?->diffForHumans() }}
                        </small>
                        @if(! empty($run->report_json['honest_diagnosis']))
                            <small>{{ \Illuminate\Support\Str::limit(implode(' — ', array_slice((array) $run->report_json['honest_diagnosis'], 0, 2)), 110) }}</small>
                        @endif
                    </div>
                    <span class="app-badge">{{ $scores['executive'] ?? 0 }}%</span>
                </div>
            @empty
                <p class="app-empty">لا توجد تشغيلات تدقيق محفوظة بعد.</p>
            @endforelse
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">لقطات المراقبة الأخيرة</h3>
        </div>
        <div class="app-list">
            @forelse ($monitorSnapshots as $snapshot)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $snapshot->project?->name ?? 'بدون مشروع' }}</strong>
                        <small>
                            {{ $snapshot->project?->client?->name ?? 'بدون عميل' }}
                            · {{ $snapshot->captured_at?->diffForHumans() }}
                        </small>
                        <small>
                            Website {{ $snapshot->website_score }}%
                            · Social {{ $snapshot->social_score }}%
                            · SEO {{ $snapshot->seo_score }}%
                        </small>
                    </div>
                    <span class="app-badge">{{ $snapshot->executive_score }}%</span>
                </div>
            @empty
                <p class="app-empty">لا توجد لقطات مراقبة بعد.</p>
            @endforelse
        </div>
    </article>
</section>

<section class="app-grid app-two-col mb-8">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">أكثر الأبعاد ضعفاً</h3>
        </div>
        <div class="app-list">
            @forelse ($weakestIntelligenceDimensions as $dimension => $count)
                <div class="app-list-item">
                    <div><strong>{{ str($dimension)->replace('_', ' ')->title() }}</strong></div>
                    <span class="app-badge">{{ $count }}</span>
                </div>
            @empty
                <p class="app-empty">لا توجد تقارير مكتملة كفاية لتجميع الأبعاد الأضعف بعد.</p>
            @endforelse
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">حالة موثوقية التحليل</h3>
        </div>
        <div class="app-list">
            @forelse ($analysisIntegrityDistribution as $status => $count)
                <div class="app-list-item">
                    <div><strong>{{ str($status)->replace('_', ' ')->title() }}</strong></div>
                    <span class="app-badge">{{ $count }}</span>
                </div>
            @empty
                <p class="app-empty">لا توجد حالات موثوقية مسجلة بعد.</p>
            @endforelse
        </div>
    </article>
</section>

<section class="app-grid app-two-col mb-8">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">تقارير ملفات المشاريع</h3>
        </div>
        <div class="app-list">
            @forelse ($briefReadiness as $item)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $item['project']->name }}</strong>
                        <small>
                            {{ $item['project']->client?->name ?? 'بدون عميل' }}
                            @if(! empty($projectActions[$item['project']->id]['headline']))
                                · {{ $projectActions[$item['project']->id]['headline'] }}
                            @endif
                        </small>
                    </div>
                    <span class="app-badge">{{ $item['assessment']['completeness_score'] ?? 0 }}%</span>
                </div>
            @empty
                <p class="app-empty">لا توجد ملفات مشاريع بعد.</p>
            @endforelse
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">أكثر الفجوات تكراراً</h3>
        </div>
        <div class="app-list">
            @forelse ($commonBriefGaps as $label => $count)
                <div class="app-list-item">
                    <div><strong>{{ $label }}</strong></div>
                    <span class="app-badge">{{ $count }}</span>
                </div>
            @empty
                <p class="app-empty">لا توجد فجوات متكررة بعد.</p>
            @endforelse
        </div>
    </article>
</section>

<section class="card mb-8">
    <div class="app-section-head">
        <h3 class="heading-sm">القرار التالي لكل مشروع</h3>
    </div>
    <div class="app-list">
        @forelse ($projectReadiness as $item)
            @php($nextAction = $projectActions[$item['project']->id] ?? null)
            <div class="app-list-item">
                <div>
                    <strong>{{ $item['project']->name }}</strong>
                    <small>{{ $nextAction['headline'] ?? 'لا توجد توصية حالياً' }}</small>
                    @if(! empty($nextAction['reason']))
                        <small>{{ $nextAction['reason'] }}</small>
                    @endif
                </div>
                <div class="app-list-item-actions">
                    <span class="app-badge">{{ $nextAction['recommended_tool_label'] ?? 'جاهز' }}</span>
                    <a href="{{ route('projects.report', $item['project']) }}" class="btn btn-secondary btn-sm">التقرير الشامل</a>
                </div>
            </div>
        @empty
            <p class="app-empty">لا توجد مشاريع يمكن اقتراح خطوة تالية لها بعد.</p>
        @endforelse
    </div>
</section>

<section class="card mb-8">
    <div class="app-section-head">
        <h3 class="heading-sm">سياق التقرير الحالي</h3>
    </div>
    <div class="app-meta-grid">
        <div><span>نوع الاستخدام</span><strong>{{ \App\Support\Dashboard\PersonaCatalog::label($profile['persona'] ?? null) }}</strong></div>
        <div><span>مستوى الفهم</span><strong>{{ \App\Support\Dashboard\AwarenessCatalog::label($profile['awareness_level'] ?? null) }}</strong></div>
        <div><span>الهدف</span><strong>{{ \App\Support\Dashboard\GoalCatalog::label($profile['primary_goal'] ?? null) }}</strong></div>
        <div><span>المسار</span><strong>{{ \App\Support\Dashboard\PathCatalog::label($profile['recommended_path'] ?? null) }}</strong></div>
    </div>
</section>

<section class="app-grid app-two-col mb-8">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">توزيع المشاريع حسب المرحلة</h3>
        </div>
        <div class="app-list">
            @forelse ($stageDistribution as $stage => $count)
                <div class="app-list-item">
                    <div><strong>{{ \App\Support\Dashboard\StageCatalog::label((int) $stage) }}</strong></div>
                    <span class="app-badge">{{ $count }}</span>
                </div>
            @empty
                <p class="app-empty">لا توجد بيانات مراحل بعد.</p>
            @endforelse
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">توزيع المشاريع حسب الحالة</h3>
        </div>
        <div class="app-list">
            @forelse ($statusDistribution as $status => $count)
                <div class="app-list-item">
                    <div><strong>{{ $status }}</strong></div>
                    <span class="app-badge">{{ $count }}</span>
                </div>
            @empty
                <p class="app-empty">لا توجد حالات مسجلة بعد.</p>
            @endforelse
        </div>
    </article>
</section>

<section class="app-grid app-two-col mb-8">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">أكثر العملاء نشاطاً</h3>
        </div>
        <div class="app-list">
            @forelse ($clientDistribution as $client)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $client->name }}</strong>
                        <small>{{ $client->status }}</small>
                    </div>
                    <span class="app-badge">{{ $client->projects_count }} مشاريع</span>
                </div>
            @empty
                <p class="app-empty">لا توجد بيانات عملاء بعد.</p>
            @endforelse
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">استخدام الأدوات حسب المرحلة</h3>
        </div>
        <div class="app-list">
            @forelse ($toolUsageByStage as $stage => $count)
                <div class="app-list-item">
                    <div><strong>{{ \App\Support\Dashboard\StageCatalog::label((int) $stage) }}</strong></div>
                    <span class="app-badge">{{ $count }} تشغيل</span>
                </div>
            @empty
                <p class="app-empty">لم يتم تسجيل استخدام أدوات بعد.</p>
            @endforelse
        </div>
    </article>
</section>

<section class="app-grid app-two-col">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">جاهزية المشاريع</h3>
        </div>
        <div class="app-list">
            @forelse ($projectReadiness as $item)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $item['project']->name }}</strong>
                        <small>
                            {{ $item['project']->client?->name ?? 'بدون عميل' }}
                            · {{ \App\Support\Dashboard\StageCatalog::label((int) ($item['journey']['current_stage'] ?? $item['project']->stage)) }}
                            · {{ $item['journey']['current_step'] ?? 'بدون خطوة' }}
                        </small>
                    </div>
                    <span class="app-badge">{{ $item['average_score'] }}%</span>
                </div>
            @empty
                <p class="app-empty">لا توجد قراءات جاهزية محفوظة حتى الآن.</p>
            @endforelse
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">آخر المخرجات المرتّبة</h3>
        </div>
        <div class="app-list">
            @forelse ($recentStructuredOutputs as $run)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $run->tool?->name ?? $run->tool_code }}</strong>
                        <small>{{ $run->project?->name ?? 'بدون مشروع' }} · {{ $run->project?->client?->name ?? 'بدون عميل' }}</small>
                    </div>
                    <span class="app-badge">{{ $run->completeness_score }}%</span>
                </div>
            @empty
                <p class="app-empty">لا توجد مخرجات مرتّبة حديثة بعد.</p>
            @endforelse
        </div>
    </article>
</section>
@endsection
