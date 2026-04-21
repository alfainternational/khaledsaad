@extends('layouts.app', ['title' => 'التقارير', 'pageTitle' => 'تقارير المساحة', 'pageKicker' => 'Reports'])

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
        <span class="app-stat-label">اعتمادات معلقة</span>
        <strong class="app-stat-value">{{ $pendingApprovalsCount }}</strong>
    </article>
    <article class="card">
        <span class="app-stat-label">اعتمادات معتمدة</span>
        <strong class="app-stat-value">{{ $approvedApprovalsCount }}</strong>
    </article>
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
