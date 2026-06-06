@extends('layouts.app', ['title' => 'الاستوديو', 'pageTitle' => 'الاستوديو الذكي', 'pageKicker' => 'AI Studio'])

@section('content')

{{-- Studio Status --}}
<section class="app-grid app-two-col mb-8">
    <x-app.card title="حالة الاستوديو">
        <div class="app-list">
            <div class="app-list-item">
                <div><strong>الوصول عبر الخطة</strong></div>
                <span class="app-badge app-badge-{{ $studioEnabled ? 'success' : 'muted' }}">{{ $studioEnabled ? 'متاح' : 'غير متاح' }}</span>
            </div>
            <div class="app-list-item">
                <div><strong>القوالب المنشورة</strong></div>
                <span class="app-badge">{{ $templates->count() }}</span>
            </div>
            <div class="app-list-item">
                <div><strong>الهدف الحالي</strong></div>
                <span class="app-badge">{{ \App\Support\Dashboard\GoalCatalog::label($profile['primary_goal'] ?? null) }}</span>
            </div>
            <div class="app-list-item">
                <div><strong>المخرجات السابقة</strong></div>
                <span class="app-badge">{{ $recentGenerations->count() }}</span>
            </div>
        </div>
    </x-app.card>

    {{-- Generation Form --}}
    <x-app.card title="توليد مسودة جديدة">
        @if ($studioEnabled || config('services.gemini.key'))
            <form method="POST" action="{{ route('studio.generations.store') }}" class="studio-form">
                @csrf
                <label class="app-field">
                    <span>القالب</span>
                    <select class="app-input" name="template_id" required>
                        <option value="">اختر القالب</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }} ({{ $template->credit_cost }} credits)</option>
                        @endforeach
                    </select>
                    @error('template_id') <small class="field-error">{{ $message }}</small> @enderror
                </label>
                <label class="app-field">
                    <span>المشروع (اختياري)</span>
                    <select class="app-input" name="project_id">
                        <option value="">بدون ربط بمشروع</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">
                                {{ $project->name }}
                                @if (! empty($projectContexts[$project->id]['journey']['current_step']))
                                    · {{ $projectContexts[$project->id]['journey']['current_step'] }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </label>
                <div class="studio-project-health">
                    <strong class="text-caption text-caption-strong">ما الذي يعرفه النظام عن مشاريعك الآن؟</strong>
                    <div class="app-list mt-3">
                        @foreach ($projects->take(3) as $project)
                            @php($briefMeta = $projectBriefs[$project->id]['assessment'] ?? ['completeness_score' => 0, 'next_actions' => []])
                            @php($projectAction = $projectActions[$project->id] ?? null)
                            @php($intelligence = $projectIntelligence[$project->id] ?? ['summary' => [], 'report' => []])
                            <div class="app-list-item">
                                <div>
                                    <strong>{{ $project->name }}</strong>
                                    <small>
                                        {{ $project->client?->name ?? 'بدون عميل' }}
                                        @if(! empty($projectAction['headline']))
                                            · {{ $projectAction['headline'] }}
                                        @endif
                                    </small>
                                    @if(! empty($intelligence['report']['honest_diagnosis'][0]))
                                        <small>{{ $intelligence['report']['honest_diagnosis'][0] }}</small>
                                    @endif
                                </div>
                                <div class="app-inline-actions">
                                    <span class="app-badge">{{ $briefMeta['completeness_score'] ?? 0 }}%</span>
                                    <span class="app-badge">{{ $intelligence['summary']['executive_score'] ?? 0 }}% تحليل</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <label class="app-field">
                    <span>ملاحظات إضافية (اختياري)</span>
                    <textarea class="app-input" name="brief" rows="3" placeholder="مثلاً: ركّز على فئة الشباب، استخدم نبرة غير رسمية..."></textarea>
                </label>
                <div class="studio-form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">توليد المسودة</button>
                </div>
            </form>
        @else
            <div class="studio-locked">
                <p>الاستوديو غير متاح في خطتك الحالية. قم بالترقية للوصول إلى قوالب الذكاء الاصطناعي.</p>
                <a href="{{ route('account.index') }}" class="btn btn-secondary">إدارة الخطة</a>
            </div>
        @endif
    </x-app.card>
</section>

{{-- Recent Generations --}}
<x-app.card title="المخرجات السابقة" class="mb-8">
    @if ($recentGenerations->isNotEmpty())
        <div class="studio-generations-list">
            @foreach ($recentGenerations as $generation)
                <a href="{{ route('studio.generations.show', $generation) }}" class="studio-generation-card">
                    <div class="studio-gen-card-head">
                        <strong>{{ $generation->template?->name ?? 'مخرج عام' }}</strong>
                        <span class="app-badge app-badge-{{ $generation->status === 'completed' ? 'success' : 'muted' }}">{{ $generation->status }}</span>
                    </div>
                    <p class="studio-gen-card-preview">{{ Str::limit($generation->output, 200) }}</p>
                    <div class="studio-gen-card-meta">
                        <small>{{ $generation->project?->name ?? 'بدون مشروع' }}</small>
                        <small>{{ $generation->created_at?->diffForHumans() }}</small>
                        <small>{{ $generation->tokens_used }} tokens</small>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <p class="app-empty">لا توجد مخرجات بعد. استخدم النموذج أعلاه لتوليد أول مسودة.</p>
    @endif
</x-app.card>

{{-- Templates Grid --}}
<section class="mb-8">
    <h3 class="heading-sm mb-4">القوالب المتاحة</h3>
    <div class="app-card-grid">
        @foreach ($templates as $template)
            <article class="card studio-template-card">
                <span class="app-badge mb-3">{{ $template->credit_cost }} credits</span>
                <h4 class="heading-sm mb-2">{{ $template->name }}</h4>
                <p class="text-body">{{ $template->description }}</p>
            </article>
        @endforeach
    </div>
</section>

{{-- Context Explainer --}}
<section class="app-grid app-two-col mb-8">
    <x-app.card title="سياق المشاريع">
        <div class="app-list">
            @forelse ($projects as $project)
                @php($context = $projectContexts[$project->id] ?? ['journey' => [], 'readiness' => []])
                @php($briefMeta = $projectBriefs[$project->id]['assessment'] ?? ['completeness_score' => 0, 'next_actions' => []])
                @php($projectAction = $projectActions[$project->id] ?? null)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $project->name }}</strong>
                        <small>
                            {{ $project->client?->name ?? 'بدون عميل' }}
                            · {{ \App\Support\Dashboard\StageCatalog::label((int) ($context['journey']['current_stage'] ?? $project->stage)) }}
                        </small>
                        @if(! empty($projectAction['reason']))
                            <small>{{ $projectAction['reason'] }}</small>
                        @endif
                    </div>
                    <div class="app-inline-actions">
                        <span class="app-badge">{{ collect($context['readiness'])->avg('score') ? (int) round((float) collect($context['readiness'])->avg('score')) : 0 }}%</span>
                        <span class="app-badge">{{ $briefMeta['completeness_score'] ?? 0 }}% brief</span>
                    </div>
                </div>
            @empty
                <p class="app-empty">لا توجد مشاريع في المساحة الحالية.</p>
            @endforelse
        </div>
    </x-app.card>

    <x-app.card title="ما الذي يستخدمه الاستوديو؟">
        <div class="app-list">
            <div class="app-list-item">
                <div>
                    <strong>ملف المشروع التسويقي</strong>
                    <small>وصف النشاط، الجمهور، العرض، التمركز، والقنوات والأولوية الحالية.</small>
                </div>
            </div>
            <div class="app-list-item">
                <div>
                    <strong>تحليل مشروعك</strong>
                    <small>آخر الدرجات، موثوقية التحليل، التشخيص الصادق، والأولويات السريعة قبل التوليد.</small>
                </div>
            </div>
            <div class="app-list-item">
                <div>
                    <strong>ملف العمل</strong>
                    <small>نوع الاستخدام، الهدف، الجمهور، والتحدي الحالي.</small>
                </div>
            </div>
            <div class="app-list-item">
                <div>
                    <strong>الرحلة الحالية</strong>
                    <small>المسار، المرحلة، والخطوة الأقرب داخل المشروع.</small>
                </div>
            </div>
            <div class="app-list-item">
                <div>
                    <strong>مخرجات الأدوات السابقة</strong>
                    <small>آخر ملخصات الأدوات المحفوظة لإثراء المسودة.</small>
                </div>
            </div>
            <div class="app-list-item">
                <div>
                    <strong>الدليل التحليلي المرجعي</strong>
                    <small>ملف يفسر شخصية العميل ولهجته وتفضيلاته وقاموسه قبل كتابة أي نص.</small>
                </div>
            </div>
        </div>
    </x-app.card>
</section>
@endsection
