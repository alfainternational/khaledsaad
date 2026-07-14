@extends('layouts.app', ['title' => $generation->template?->name ?? 'مخرج الاستوديو', 'pageTitle' => 'مخرج الاستوديو', 'pageKicker' => 'AI Studio'])

@section('content')
@php
    $titledSections = collect($sections ?? [])->filter(fn (array $section): bool => ($section['title'] ?? '') !== '')->values();
    $canExport = (bool) entitlement('outputs.can_export');
@endphp

<section class="studio-gen-header mb-6">
    <div>
        <h2 class="heading-lg">{{ $generation->template?->name ?? 'مخرج AI' }}</h2>
        <p class="text-muted">
            {{ $generation->project?->name ?? 'بدون مشروع' }}
            · {{ $generation->author?->name ?? '' }}
            · {{ $generation->created_at?->format('Y-m-d H:i') }}
        </p>
    </div>
    <div class="studio-gen-actions studio-gen-actions-wrap">
        <a href="{{ route('studio.index') }}" class="btn btn-secondary">العودة للاستوديو</a>
        @if ($canExport)
            <button type="button" class="btn btn-primary" data-copy-studio>نسخ المخرج</button>
            <a href="{{ route('studio.generations.export', [$generation, 'md']) }}" class="btn btn-secondary">تنزيل Markdown</a>
            <a href="{{ route('studio.generations.export', [$generation, 'html']) }}" class="btn btn-secondary">تنزيل HTML</a>
            <a href="{{ route('studio.generations.export', [$generation, 'pdf']) }}" class="btn btn-secondary">تنزيل PDF</a>
        @else
            <a href="{{ route('billing.index') }}" class="btn btn-primary">رقِّ اشتراكك للنسخ والتصدير</a>
        @endif
    </div>
</section>

@if (in_array($generation->status, ['queued', 'processing'], true))
    <section class="card mb-6 studio-pending" data-generation-poll="{{ route('studio.generations.status', $generation) }}">
        <span class="studio-pending-spinner" aria-hidden="true"></span>
        <div>
            <h3 class="heading-sm">جارٍ توليد ملفك الآن</h3>
            <p class="text-muted">نُجهّز التحليل الكامل ونربط بيانات مشروعك. سيظهر الملف تلقائياً بمجرد جهوزيته — لا حاجة لتحديث الصفحة.</p>
        </div>
    </section>
@elseif ($generation->status === 'failed')
    <section class="card mb-6">
        <div class="app-section-head">
            <h3 class="heading-sm">تعذّر إكمال التوليد</h3>
            <span class="app-badge app-badge-danger">failed</span>
        </div>
        <p class="text-body">حدث خطأ أثناء التوليد. جرّب توليد مسودة جديدة من الاستوديو.</p>
    </section>
@endif

@if ($generation->status === 'needs_input')
    <section class="card mb-6">
        <div class="app-section-head">
            <h3 class="heading-sm">الاستوديو أوقف التوليد النهائي</h3>
            <span class="app-badge app-badge-muted">needs_input</span>
        </div>
        <p class="text-body">
            لم يتم اعتماد ملف نهائي لأن المدخلات أو الإشارات التحليلية غير كافية لهذا القالب بعد.
            راجع الأسئلة والبيانات الناقصة داخل الملف ثم أعد التوليد.
        </p>
    </section>
@endif

<section class="app-grid app-two-col mb-6">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">تفاصيل التوليد</h3>
        </div>
        <div class="app-list">
            <div class="app-list-item">
                <div><strong>القالب</strong></div>
                <span>{{ $generation->template?->name ?? '—' }}</span>
            </div>
            @if($generation->template?->domain)
                <div class="app-list-item">
                    <div><strong>المجال</strong></div>
                    <span>{{ $generation->template->domain }}</span>
                </div>
            @endif
            <div class="app-list-item">
                <div><strong>المشروع</strong></div>
                <span>{{ $generation->project?->name ?? 'غير مرتبط' }}</span>
            </div>
            @if ($generation->project?->client)
                <div class="app-list-item">
                    <div><strong>العميل</strong></div>
                    <span>{{ $generation->project->client->name }}</span>
                </div>
            @endif
            <div class="app-list-item">
                <div><strong>الحالة</strong></div>
                <span class="app-badge app-badge-{{ $generation->status === 'completed' ? 'success' : 'muted' }}">{{ $generation->status }}</span>
            </div>
            <div class="app-list-item">
                <div><strong>التوكنات</strong></div>
                <span>{{ number_format($generation->tokens_used ?? 0) }}</span>
            </div>
            <div class="app-list-item">
                <div><strong>التاريخ</strong></div>
                <span>{{ $generation->created_at?->diffForHumans() }}</span>
            </div>
        </div>
    </article>

    @if (!empty($generation->inputs_json['brief']))
        <article class="card">
            <div class="app-section-head">
                <h3 class="heading-sm">الملاحظات المُدخلة</h3>
            </div>
            <div class="studio-gen-brief">
                {{ $generation->inputs_json['brief'] }}
            </div>
        </article>
    @endif
</section>

@if (! empty($specialistReview['panels']))
    <section class="card mb-6">
        <div class="app-section-head">
            <h3 class="heading-sm">مراجعة الأخصائيين</h3>
            @if (! is_null($specialistReview['score']))
                <span class="app-badge">{{ $specialistReview['score'] }}%</span>
            @endif
        </div>
        <div class="specialist-review-body mt-4">
            @foreach ($specialistReview['panels'] as $panel)
                @php $tier = $panel['score'] >= 80 ? 'good' : ($panel['score'] >= 50 ? 'warn' : 'low'); @endphp
                <div class="specialist-panel">
                    <div class="specialist-panel-head">
                        <span class="specialist-panel-name">{{ $panel['name'] }}</span>
                        <span class="specialist-panel-score specialist-tier-{{ $tier }}">{{ $panel['score'] }}%</span>
                    </div>
                    @if (! empty($panel['items']))
                        <ul class="specialist-items">
                            @foreach (array_slice($panel['items'], 0, 4) as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif

@if (!empty($generation->inputs_json['analysis_dossier']['guide_markdown']))
    <details class="card mb-6 studio-context-details" open>
        <summary class="heading-sm studio-context-summary">الدليل التحليلي المرجعي المستخدم قبل التوليد</summary>
        <div class="studio-rich-text studio-context-body mt-4">
            {!! \App\Support\AI\StudioContentRenderer::render($generation->inputs_json['analysis_dossier']['guide_markdown']) !!}
        </div>
    </details>
@endif

@if (!empty($generation->inputs_json['template_meta']) || !empty($generation->inputs_json['tool_summaries']))
    <details class="card mb-6 studio-context-details">
        <summary class="heading-sm studio-context-summary">ما الذي استُخدم كسياق للتوليد</summary>
        <div class="studio-gen-brief studio-context-body mt-4 text-muted">
            @if (!empty($generation->inputs_json['template_meta']['code']))
                <p><strong>قالب:</strong> {{ $generation->inputs_json['template_meta']['code'] }}</p>
            @endif
            @if (!empty($generation->inputs_json['tool_summaries']) && is_array($generation->inputs_json['tool_summaries']))
                <p><strong>عدد ملخصات الأدوات:</strong> {{ count($generation->inputs_json['tool_summaries']) }}</p>
            @endif
            @if (!empty($generation->inputs_json['analysis_dossier']['compiled_at']))
                <p><strong>آخر تحديث للدليل التحليلي:</strong> {{ $generation->inputs_json['analysis_dossier']['compiled_at'] }}</p>
            @endif
        </div>
    </details>
@endif

@unless (in_array($generation->status, ['queued', 'processing'], true))
@if ($titledSections->isNotEmpty())
    <section class="card mb-6 studio-outline-card">
        <div class="app-section-head">
            <h3 class="heading-sm">محتويات الملف</h3>
            <span class="app-badge">{{ $titledSections->count() }} أقسام</span>
        </div>
        <div class="studio-outline-list">
            @foreach ($titledSections as $section)
                <a href="#{{ $section['id'] }}" class="studio-outline-link">
                    <strong>{{ $section['title'] }}</strong>
                    @if (!empty($section['excerpt']))
                        <span>{{ $section['excerpt'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
@endif

<section class="studio-gen-layout mb-8">
    @unless ($canExport)
        <div class="gate-banner" data-read-only-banner>
            <span class="gate-banner-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 1 1 8 0v4"/></svg>
            </span>
            <div>
                <strong>نسخة للقراءة فقط</strong>
                <p>هذا المخرج متاح للقراءة داخل المنصة. اشترك لنسخه وتصديره وطباعته واستخدامه في التنفيذ.</p>
            </div>
            <a href="{{ route('billing.index') }}" class="btn btn-primary">عرض الباقات</a>
        </div>
    @endunless

    <div class="studio-gen-main {{ $canExport ? '' : 'gate-locked' }}" id="studio-output" data-studio-output @unless($canExport) data-locked-output @endunless>
        @foreach ($sections as $section)
            <article class="card studio-output-card {{ $canExport ? '' : 'gate-watermark' }}" id="{{ $section['id'] }}">
                @if (($section['title'] ?? '') !== '')
                    <div class="studio-output-card-head">
                        <h3 class="heading-sm studio-output-section-title">{{ $section['title'] }}</h3>
                        <a href="#{{ $section['id'] }}" class="studio-anchor-link" aria-label="رابط القسم">#</a>
                    </div>
                @endif
                <div class="studio-rich-text studio-output-section-body">
                    {!! $section['html'] !!}
                </div>
            </article>
        @endforeach
    </div>
</section>
@endunless

<section class="studio-gen-footer mb-8">
    <a href="{{ route('studio.index') }}" class="btn btn-secondary btn-lg">توليد مسودة جديدة</a>
    <form method="POST" action="{{ route('studio.generations.destroy', $generation) }}" onsubmit="return confirm('حذف هذا المخرج؟')">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-danger">حذف المخرج</button>
    </form>
</section>
@endsection
