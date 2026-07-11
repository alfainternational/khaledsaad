@extends('layouts.app', ['title' => $project->name, 'pageTitle' => $project->name, 'pageKicker' => 'مشروع'])

@section('content')
<section class="app-grid app-two-col mb-8">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">تفاصيل المشروع</h3>
            <a href="{{ route('projects.edit', $project) }}" class="btn btn-secondary btn-sm">تعديل</a>
        </div>
        <div class="app-meta-grid">
            <div><span>العميل</span><strong>{{ $project->client?->name ?? 'بدون عميل' }}</strong></div>
            <div><span>المرحلة</span><strong>{{ \App\Support\Dashboard\StageCatalog::label((int) $project->stage) }}</strong></div>
            <div><span>الحالة</span><strong>{{ $project->status }}</strong></div>
            <div><span>المعرّف</span><strong>{{ $project->public_id }}</strong></div>
            <div><span>القطاع</span><strong>{{ app(\App\Support\Intelligence\SectorTemplateCatalog::class)->options()[$project->sector] ?? $project->sector }}</strong></div>
            <div><span>السوق</span><strong>{{ $project->market_country ?: 'غير محدد' }}</strong></div>
            <div><span>الدومين</span><strong>{{ $project->primary_domain ?: 'غير مضاف' }}</strong></div>
            <div><span>المراقبة</span><strong>{{ $project->monitoring_enabled ? 'مفعلة' : 'غير مفعلة' }}</strong></div>
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">حركة المشروع</h3>
        </div>
        <div class="app-list">
            <div class="app-list-item">
                <div>
                    <strong>تشغيل الأدوات</strong>
                    <small>الاستوديو بعد التقرير</small>
                </div>
                <span class="app-badge">{{ $project->tool_runs_count }}</span>
            </div>
            <div class="app-list-item">
                <div>
                    <strong>الموافقات</strong>
                    <small>العناصر التي تحتاج مراجعة</small>
                </div>
                <span class="app-badge">{{ $project->approvals_count }}</span>
            </div>
            <div class="app-list-item">
                <div>
                    <strong>آخر تقرير تحليل</strong>
                    <small>
                        @if($latestAudit?->status === 'completed')
                            {{ $latestAudit?->completed_at?->diffForHumans() ?? 'اكتمل الآن' }}
                        @elseif($latestAudit?->status === 'failed')
                            فشل آخر تشغيل
                        @elseif(in_array($latestAudit?->status, ['queued', 'running'], true))
                            قيد التنفيذ الآن
                        @else
                            لم يتم تشغيله بعد
                        @endif
                    </small>
                </div>
                <span class="app-badge">
                    @if($latestAudit?->status === 'queued')
                        قيد الانتظار
                    @elseif($latestAudit?->status === 'running')
                        قيد التشغيل
                    @elseif($latestAudit?->status === 'failed')
                        فشل
                    @else
                        {{ $latestAuditReport['executive_scores']['executive'] ?? '--' }}
                    @endif
                </span>
            </div>
        </div>
    </article>
</section>

@if ($topExecutionRecommendations->isNotEmpty())
    @php($impactLabels = ['high' => 'أثر عالٍ', 'medium' => 'أثر متوسط', 'low' => 'أثر منخفض'])
    <section class="project-priority-panel mb-8" aria-labelledby="project-priority-title">
        <div class="project-priority-head">
            <div>
                <p class="section-kicker">ابدأ من هنا</p>
                <h3 id="project-priority-title" class="heading-sm">أولويات التنفيذ الآن</h3>
                <p class="text-muted">أقصر قائمة عمل مستخرجة من توصيات التحليل، حتى تعرف ماذا تفعل قبل قراءة التقرير الطويل.</p>
            </div>
            <a href="{{ route('projects.recommendations.index', $project) }}" class="btn btn-primary btn-sm">فتح التوصيات والتنفيذ</a>
        </div>

        <div class="project-priority-list">
            @foreach ($topExecutionRecommendations as $index => $recommendation)
                @php($package = $recommendation->executionPackages->first())
                <article class="project-priority-item" data-project-priority-title="{{ $recommendation->title }}">
                    <span class="exec-rank">{{ $index + 1 }}</span>
                    <div class="project-priority-copy">
                        <h4>{{ $recommendation->title }}</h4>
                        <p>{{ $recommendation->rationale ?: ($recommendation->evidence ?: 'حوّل هذه الأولوية إلى حزمة تنفيذ لتحديد الخطوة التالية.') }}</p>
                        <small>{{ $impactLabels[$recommendation->estimated_impact] ?? $recommendation->estimated_impact }} · ثقة {{ round($recommendation->confidence * 100) }}%</small>
                    </div>
                    <div class="project-priority-action">
                        @if ($package)
                            <a href="{{ route('execution-packages.show', $package) }}" class="btn btn-secondary btn-sm">عرض التنفيذ</a>
                        @else
                            <a href="{{ route('projects.recommendations.index', $project) }}" class="btn btn-secondary btn-sm">تجهيز التنفيذ</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif

@php($agencyVerdict = $latestAgencyAuditRun?->summary_json['agency_verdict'] ?? [])
@if (! empty($agencyVerdict))
    <section class="card mb-8">
        <div class="app-section-head">
            <div>
                <p class="section-kicker">مراقبة الوكالة</p>
                <h3 class="heading-sm">حكم الوكالة الحالي</h3>
                <p class="text-caption">مختصر آخر تقييم لعمل الوكالة حتى تعرف هل تطلب تصحيحاً أم تتابع بثقة.</p>
            </div>
            @if ($latestAgencyAuditRun?->tool)
                <a href="{{ route('tools.show', $latestAgencyAuditRun->tool) }}" class="btn btn-secondary btn-sm">فتح تقييم الوكالة</a>
            @endif
        </div>

        <div class="app-meta-grid mb-4">
            <div><span>القرار</span><strong>{{ $agencyVerdict['decision'] ?? 'راجع التفاصيل' }}</strong></div>
            <div><span>مستوى المخاطرة</span><strong>{{ $agencyVerdict['risk_level'] ?? 'غير محدد' }}</strong></div>
            <div><span>درجة التشغيل</span><strong>{{ $agencyVerdict['score'] ?? '--' }}%</strong></div>
        </div>

        @if (! empty($agencyVerdict['demands']))
            <div class="app-list">
                @foreach (array_slice($agencyVerdict['demands'], 0, 3) as $demand)
                    <div class="app-list-item">
                        <div>
                            <strong>اطلب من الوكالة</strong>
                            <small>{{ $demand }}</small>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if (! empty($agencyVerdict['meeting_brief']))
            <div class="app-list mt-4">
                <div class="app-list-item">
                    <div>
                        <strong>رسالة الاجتماع مع الوكالة</strong>
                        <small>{!! nl2br(e($agencyVerdict['meeting_brief'])) !!}</small>
                    </div>
                    <form method="POST" action="{{ route('projects.approvals.store', $project) }}">
                        @csrf
                        <input type="hidden" name="item_type" value="tool_run">
                        <input type="hidden" name="item_id" value="{{ $latestAgencyAuditRun->id }}">
                        <input type="hidden" name="note" value="{{ $agencyVerdict['meeting_brief'] }}">
                        <button type="submit" class="btn btn-primary btn-sm">طلب اعتماد مطالب الوكالة</button>
                    </form>
                </div>
            </div>
        @endif
    </section>
@endif

<section class="card mb-8">
    @php($analysisIntegrity = $latestAuditReport['analysis_integrity'] ?? [])

    <div class="app-section-head">
        <div>
            <h3 class="heading-sm">تحليل مشروعك</h3>
            <p class="text-caption">الموقع + الحسابات + المنافسون + الجاهزية + جهات الاتصال + خطة العمل.</p>
        </div>
        <div class="app-inline-actions">
            <a href="{{ route('projects.edit', $project) }}" class="btn btn-secondary btn-sm">تحديث البيانات</a>
            <a href="{{ route('projects.recommendations.index', $project) }}" class="btn btn-secondary btn-sm">التوصيات والتنفيذ</a>
            <a href="{{ route('projects.report', $project) }}" class="btn btn-primary btn-sm">التقرير الشامل</a>
            <form method="POST" action="{{ route('projects.audit.run', $project) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">تشغيل التحليل الآن</button>
            </form>
        </div>
    </div>

    @if($latestAudit?->status === 'queued')
        <p class="app-empty mb-6">تمت جدولة التحليل. سيبدأ التنفيذ تلقائياً، وسيظهر التقرير هنا فور اكتماله.</p>
    @elseif($latestAudit?->status === 'running')
        <p class="app-empty mb-6">التحليل قيد التشغيل حالياً. يتم الآن جمع أدلة الموقع والسوشيال والمنافسين لهذا المشروع.</p>
    @elseif($latestAudit?->status === 'failed')
        <p class="app-empty mb-6">
            فشل آخر تشغيل للتحليل.
            {{ $latestAudit?->error_json['message'] ?? 'تحقق من المدخلات أو أعد التشغيل.' }}
        </p>
    @endif

    @if($analysisIntegrity !== [])
        <article class="card mb-6">
            <div class="app-section-head">
                <h3 class="heading-sm">موثوقية التحليل</h3>
                <span class="app-badge">{{ $analysisIntegrity['label'] ?? 'غير محدد' }}</span>
            </div>
            <div class="app-list">
                <div class="app-list-item">
                    <div>
                        <strong>{{ $analysisIntegrity['summary'] ?? 'لا توجد قراءة موثوقية محفوظة بعد.' }}</strong>
                    </div>
                </div>
                @foreach (($analysisIntegrity['highlights'] ?? []) as $line)
                    <div class="app-list-item">
                        <div><small>{{ $line }}</small></div>
                    </div>
                @endforeach
                @foreach (($analysisIntegrity['warnings'] ?? []) as $warning)
                    <div class="app-list-item">
                        <div><small>{{ $warning }}</small></div>
                    </div>
                @endforeach
            </div>
        </article>
    @endif

    @if(($analysisIntegrity['status'] ?? null) === 'insufficient')
        <p class="app-empty mb-6">تم إخفاء الدرجات لأن التغطية الحالية لا تكفي لعرض قراءة تبدو نهائية. أضف مصادر أو أعد التشغيل بعد إتاحة الوصول.</p>
    @else
        <div class="app-stat-grid mb-6">
            @forelse (($latestAuditReport['executive_scores'] ?? []) as $label => $score)
                <article class="card">
                    <span class="app-stat-label">{{ str($label)->replace('_', ' ')->title() }}</span>
                    <strong class="app-stat-value">{{ $score }}/100</strong>
                </article>
            @empty
                <p class="app-empty">أضف الدومين والروابط الرسمية ثم شغّل التحليل لتظهر الدرجات هنا.</p>
            @endforelse
        </div>
    @endif

    <div class="app-grid app-two-col mb-6">
        <article class="card">
            <div class="app-section-head">
                <h3 class="heading-sm">تشخيص صادق</h3>
            </div>
            <div class="app-list">
                @forelse (($latestAuditReport['honest_diagnosis'] ?? []) as $line)
                    <div class="app-list-item">
                        <div><strong>{{ $line }}</strong></div>
                    </div>
                @empty
                    <p class="app-empty">لا يوجد تشخيص محفوظ بعد.</p>
                @endforelse
            </div>
        </article>

        <article class="card">
            <div class="app-section-head">
                <h3 class="heading-sm">قنوات التواصل الرسمية</h3>
            </div>
            <div class="app-list">
                @forelse (($latestAuditReport['official_contacts'] ?? []) as $contact)
                    <div class="app-list-item">
                        <div>
                            <strong>{{ $contact['contact_type'] ?? 'contact' }}</strong>
                            <small>{{ $contact['source_url'] ?? '' }}</small>
                        </div>
                        <span class="app-badge">{{ $contact['contact_value'] ?? '' }}</span>
                    </div>
                @empty
                    <p class="app-empty">لم تُستخرج جهات اتصال رسمية بعد.</p>
                @endforelse
            </div>
        </article>
    </div>

    <article class="card mb-6">
        <div class="app-section-head">
            <h3 class="heading-sm">التحقق اليدوي للسوشيال</h3>
        </div>
        <div class="app-list">
            @forelse (($project->verified_social_profiles_json ?? []) as $profile)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $profile['title'] ?? ($profile['network'] ?? 'Social Profile') }}</strong>
                        <small>{{ $profile['network'] ?? 'social' }} · {{ $profile['url'] ?? '' }}</small>
                        @if (! empty($profile['description']))
                            <small>{{ $profile['description'] }}</small>
                        @endif
                    </div>
                    <div class="app-inline-actions">
                        @if (! empty($profile['handle']))
                            <span class="app-badge">{{ $profile['handle'] }}</span>
                        @endif
                        @if (! empty($profile['primary_cta']))
                            <span class="app-badge">{{ $profile['primary_cta'] }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="app-empty">لا توجد حسابات سوشيال موثقة يدوياً داخل هذا المشروع بعد.</p>
            @endforelse
        </div>
    </article>

    <div class="app-grid app-two-col mb-6">
        <article class="card">
            <div class="app-section-head">
                <h3 class="heading-sm">أهم 5 مشاكل</h3>
            </div>
            <div class="app-list">
                @forelse (($latestAuditReport['top_5_problems'] ?? []) as $line)
                    <div class="app-list-item">
                        <div><strong>{{ $line }}</strong></div>
                    </div>
                @empty
                    <p class="app-empty">لا توجد مشاكل مرتبة بعد.</p>
                @endforelse
            </div>
        </article>

        <article class="card">
            <div class="app-section-head">
                <h3 class="heading-sm">أهم 5 فرص</h3>
            </div>
            <div class="app-list">
                @forelse (($latestAuditReport['top_5_opportunities'] ?? []) as $line)
                    <div class="app-list-item">
                        <div><strong>{{ $line }}</strong></div>
                    </div>
                @empty
                    <p class="app-empty">لا توجد فرص مرتبة بعد.</p>
                @endforelse
            </div>
        </article>
    </div>

    <div class="app-grid app-two-col mb-6">
        <article class="card">
            <div class="app-section-head">
                <h3 class="heading-sm">مكاسب سريعة خلال 7 أيام</h3>
            </div>
            <div class="app-list">
                @forelse (($latestAuditReport['priority_actions']['quick_wins_7_days'] ?? []) as $line)
                    <div class="app-list-item">
                        <div><strong>{{ $line }}</strong></div>
                    </div>
                @empty
                    <p class="app-empty">{{ ($analysisIntegrity['status'] ?? null) === 'insufficient' ? 'تم إيقاف المكاسب السريعة الواسعة لأن الأدلة الحالية غير كافية.' : 'لا توجد مكاسب سريعة محفوظة بعد.' }}</p>
                @endforelse
            </div>
        </article>

        <article class="card">
            <div class="app-section-head">
                <h3 class="heading-sm">تحسينات 30 يوم</h3>
            </div>
            <div class="app-list">
                @forelse (($latestAuditReport['priority_actions']['improvements_30_days'] ?? []) as $line)
                    <div class="app-list-item">
                        <div><strong>{{ $line }}</strong></div>
                    </div>
                @empty
                    <p class="app-empty">{{ ($analysisIntegrity['status'] ?? null) === 'insufficient' ? 'لن تظهر تحسينات 30 يوم قبل توفر قراءة موثوقة كفاية.' : 'لا توجد تحسينات 30 يوم محفوظة بعد.' }}</p>
                @endforelse
            </div>
        </article>
    </div>

    <div class="app-grid app-two-col">
        <article class="card">
            <div class="app-section-head">
                <h3 class="heading-sm">تحسينات استراتيجية 90 يوم</h3>
            </div>
            <div class="app-list">
                @forelse (($latestAuditReport['priority_actions']['strategic_90_days'] ?? []) as $line)
                    <div class="app-list-item">
                        <div><strong>{{ $line }}</strong></div>
                    </div>
                @empty
                    <p class="app-empty">{{ ($analysisIntegrity['status'] ?? null) === 'insufficient' ? 'تم تعطيل الخطة الاستراتيجية هنا لأن التغطية الحالية لا تدعم استنتاجاً بعيد المدى.' : 'لا توجد تحسينات استراتيجية محفوظة بعد.' }}</p>
                @endforelse
            </div>
        </article>

        <article class="card">
            <div class="app-section-head">
                <h3 class="heading-sm">لمحة عن منافسيك</h3>
            </div>
            <div class="app-list">
                @forelse (($latestAuditReport['competitor_snapshot']['leaders'] ?? []) as $competitor)
                    <div class="app-list-item">
                        <div>
                            <strong>{{ $competitor['label'] ?? 'منافس' }}</strong>
                            <small>المقارنة الظاهرة</small>
                        </div>
                        <span class="app-badge">{{ $competitor['executive_score'] ?? 0 }}/100</span>
                    </div>
                @empty
                    <p class="app-empty">{{ $latestAuditReport['competitor_snapshot']['summary'] ?? 'لا توجد بيانات منافسين بعد.' }}</p>
                @endforelse
            </div>
        </article>
    </div>
</section>

<section class="card mb-8">
    <div class="app-section-head">
        <h3 class="heading-sm">التغيّر قبل / بعد</h3>
    </div>
    <div class="app-list">
        @forelse ($monitoringTrend as $point)
            <div class="app-list-item">
                <div>
                    <strong>{{ $point['captured_at'] }}</strong>
                    <small>الموقع {{ $point['website_score'] }} · الحسابات {{ $point['social_score'] }} · البحث {{ $point['seo_score'] }} · التحويل {{ $point['conversion_score'] }}</small>
                </div>
                <span class="app-badge">{{ $point['executive_score'] }}/100</span>
            </div>
        @empty
            <p class="app-empty">سيتكوّن الاتجاه بعد إعادة التحليل أو عند تفعيل المراقبة الدورية.</p>
        @endforelse
    </div>
</section>

<section class="app-grid app-two-col mb-8">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">بيانات المشروع وملفه</h3>
            <a href="{{ route('projects.brief.edit', $project) }}" class="btn btn-secondary btn-sm">تحديث الملف</a>
        </div>
        <div class="app-list">
            <div class="app-list-item">
                <div>
                    <strong>درجة اكتمال الملف</strong>
                    <small>{{ $briefAssessment['known_fields'] ?? 0 }} من {{ $briefAssessment['total_fields'] ?? 0 }} حقول أساسية</small>
                </div>
                <span class="app-badge">{{ $briefAssessment['completeness_score'] ?? 0 }}%</span>
            </div>
            @foreach (($briefAssessment['reports']['executive_brief'] ?? []) as $line)
                <div class="app-list-item">
                    <div><small>{{ $line }}</small></div>
                </div>
            @endforeach
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">لقطة الرحلة الحالية</h3>
        </div>
        <div class="app-meta-grid">
            <div><span>المسار</span><strong>{{ \App\Support\Dashboard\PathCatalog::label($journeySnapshot['path'] ?? null) }}</strong></div>
            <div><span>المرحلة الحالية</span><strong>{{ \App\Support\Dashboard\StageCatalog::label((int) ($journeySnapshot['current_stage'] ?? $project->stage)) }}</strong></div>
            <div><span>الخطوة الحالية</span><strong>{{ $journeySnapshot['current_step'] ?? 'غير محدد' }}</strong></div>
            <div><span>الأدوات المكتملة</span><strong>{{ $journeySnapshot['completed_count'] ?? 0 }}</strong></div>
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">جاهزية المشروع</h3>
        </div>
        <div class="app-list">
            @forelse ($readiness as $dimension)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $dimension['label'] }}</strong>
                        <small>{{ $dimension['completed'] }} من {{ $dimension['total'] }} عناصر</small>
                    </div>
                    <span class="app-badge">{{ $dimension['score'] }}%</span>
                </div>
            @empty
                <p class="app-empty">ستظهر القراءة بعد تشغيل الأدوات الأساسية لهذا المشروع.</p>
            @endforelse
        </div>
    </article>
</section>

<section class="app-grid app-two-col mb-8">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">الاستوديو الذكي</h3>
        </div>
        <div class="app-list">
            @forelse ($availableTools as $tool)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $tool->name ?: $tool->code }}</strong>
                        <small>{{ \App\Support\Dashboard\StageCatalog::label((int) $tool->stage) }} · {{ $tool->status }}</small>
                    </div>
                    <div class="app-inline-actions">
                        <a href="{{ route('tools.show', $tool) }}" class="btn btn-secondary btn-sm">تفاصيل</a>
                        <form method="POST" action="{{ route('projects.tools.run', [$project, $tool]) }}">
                            @csrf
                            <input type="hidden" name="mode" value="guided">
                            <button type="submit" class="btn btn-primary btn-sm">تشغيل سريع</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="app-empty">لا توجد أدوات منشورة حالياً.</p>
            @endforelse
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">آخر تشغيلات الأدوات</h3>
        </div>
        <div class="app-list">
            @forelse ($recentRuns as $run)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $run->tool?->name ?: $run->tool_code }}</strong>
                        <small>{{ $run->created_at?->diffForHumans() }} · {{ $run->mode }}</small>
                        @if (! empty($run->next_actions_json[0]))
                            <small>خطوة هذا التشغيل: {{ $run->next_actions_json[0] }}</small>
                        @endif
                    </div>
                    <div class="app-inline-actions">
                        <span class="app-badge">{{ $run->summary_json['headline'] ?? $run->output_json['headline'] ?? 'output' }}</span>
                        <span class="app-badge">{{ $run->completeness_score }}%</span>
                        @if ($run->tool)
                            <a href="{{ route('tools.show', $run->tool) }}" class="btn btn-secondary btn-sm">فتح النتيجة</a>
                        @endif
                        <form method="POST" action="{{ route('projects.approvals.store', $project) }}">
                            @csrf
                            <input type="hidden" name="item_type" value="tool_run">
                            <input type="hidden" name="item_id" value="{{ $run->id }}">
                            <button type="submit" class="btn btn-ghost btn-sm">طلب اعتماد</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="app-empty">لم يتم تشغيل أي أداة على هذا المشروع بعد.</p>
            @endforelse
        </div>
    </article>
</section>

<section class="app-grid app-two-col mb-8">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">آخر مخرجات الاستوديو</h3>
        </div>
        <div class="app-list">
            @forelse ($recentGenerations as $generation)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $generation->template?->name ?? 'مخرج عام' }}</strong>
                        <small>{{ $generation->created_at?->diffForHumans() }}</small>
                    </div>
                    <div class="app-inline-actions">
                        <span class="app-badge">{{ $generation->tokens_used }} وحدة</span>
                        <form method="POST" action="{{ route('projects.approvals.store', $project) }}">
                            @csrf
                            <input type="hidden" name="item_type" value="ai_generation">
                            <input type="hidden" name="item_id" value="{{ $generation->id }}">
                            <button type="submit" class="btn btn-ghost btn-sm">طلب اعتماد</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="app-empty">لا توجد مخرجات ذكاء اصطناعي مرتبطة بهذا المشروع بعد.</p>
            @endforelse
        </div>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">مخرجات المشروع المحفوظة</h3>
        </div>
        <div class="app-list">
            @forelse ($projectWorkspaceData as $item)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $item->key }}</strong>
                        <small>{{ $item->updated_at?->diffForHumans() }}</small>
                    </div>
                    <span class="app-badge">{{ $item->value_json['tool_name'] ?? ($item->value_json['headline'] ?? $item->value_json['text'] ?? 'data') }}</span>
                </div>
            @empty
                <p class="app-empty">لم يتم حفظ مخرجات تشغيلية داخل هذا المشروع بعد.</p>
            @endforelse
        </div>
    </article>
</section>
@endsection
