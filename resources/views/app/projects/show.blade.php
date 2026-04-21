@extends('layouts.app', ['title' => $project->name, 'pageTitle' => $project->name, 'pageKicker' => 'Project'])

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
            <div><span>Public ID</span><strong>{{ $project->public_id }}</strong></div>
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
                    <small>مرات الاستخدام داخل المشروع</small>
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
                    <strong>مخرجات AI</strong>
                    <small>المسودات المرتبطة بالمشروع</small>
                </div>
                <span class="app-badge">{{ $recentGenerations->count() }}</span>
            </div>
        </div>
    </article>
</section>

<section class="app-grid app-two-col mb-8">
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
            <h3 class="heading-sm">تشغيل الأدوات</h3>
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
                    </div>
                    <div class="app-inline-actions">
                        <span class="app-badge">{{ $run->summary_json['headline'] ?? $run->output_json['headline'] ?? 'output' }}</span>
                        <span class="app-badge">{{ $run->completeness_score }}%</span>
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
                        <span class="app-badge">{{ $generation->tokens_used }} tokens</span>
                        <form method="POST" action="{{ route('projects.approvals.store', $project) }}">
                            @csrf
                            <input type="hidden" name="item_type" value="ai_generation">
                            <input type="hidden" name="item_id" value="{{ $generation->id }}">
                            <button type="submit" class="btn btn-ghost btn-sm">طلب اعتماد</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="app-empty">لا توجد مخرجات AI مرتبطة بهذا المشروع بعد.</p>
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
