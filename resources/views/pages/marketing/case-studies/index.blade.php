@extends('layouts.marketing', ['title' => $title, 'description' => $description])

@section('content')
@php
    $impactStats = [
        ['value' => $caseStudies->total() ?? $caseStudies->count(), 'label' => 'دراسات حالة موثّقة'],
        ['value' => '+', 'label' => 'قطاعات متعددة'],
        ['value' => 'ROI', 'label' => 'تركيز على العائد'],
        ['value' => '100%', 'label' => 'بيانات حقيقية'],
    ];
@endphp

{{-- ═══ Hero ═══ --}}
<section class="section-lg internal-page-hero">
    <div class="site-container">
        <div class="section-header reveal max-w-3xl mx-auto">
            <div class="section-badge section-badge-center mb-4">
                <span class="section-dot"></span>
                <span class="section-badge-text">دراسات الحالة</span>
            </div>
            <h1 class="heading-lg mb-4">
                نتائج حقيقية من <span class="text-gradient">مشاريع مثل مشروعك</span>
            </h1>
            <p class="text-body-lg max-w-2xl mx-auto">
                قصص حقيقية لمشاريع طبّقت خطوات المنصة على أرض الواقع. أرقام، تحديات، قرارات، ونتائج — لا كلام تسويقي فضفاض.
            </p>
        </div>
    </div>
</section>

{{-- ═══ ملخص الأثر ═══ --}}
<section class="section-band bg-2">
    <div class="site-container">
        <div class="four-col">
            @foreach($impactStats as $i => $stat)
            <article class="metric-card reveal d-{{ $i + 1 }}">
                <div class="metric-top-bar" aria-hidden="true"></div>
                <p class="metric-value">{{ $stat['value'] }}</p>
                <p class="metric-label">{{ $stat['label'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ شبكة دراسات الحالة ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">القصص الكاملة</p>
            <h2 class="heading-lg mb-4">من <span class="text-gradient">التحدي إلى النتيجة</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                كل دراسة حالة تتبع نفس الخطوات: السياق، التحدي، القرار، التنفيذ، الأرقام النهائية.
            </p>
        </div>

        @if($caseStudies->isEmpty())
            <div class="page-summary-card text-center max-w-2xl mx-auto">
                <p class="text-body">لا توجد دراسات منشورة بعد. سنبدأ بنشر أولى الدراسات قريباً.</p>
                <div class="mt-4">
                    <a href="{{ route('contact') }}" class="btn btn-primary btn-lg">شاركنا قصة مشروعك</a>
                </div>
            </div>
        @else
            <div class="cases-grid">
                @foreach ($caseStudies as $i => $study)
                    <article class="case-card reveal d-{{ ($i % 3) + 1 }}">
                        @if($study->cover_image)
                            <a href="{{ route('case-studies.show', $study->slug) }}" class="case-card-image">
                                <img src="{{ asset('storage/'.$study->cover_image) }}" alt="" loading="lazy">
                            </a>
                        @endif
                        <div class="case-card-body">
                            <div class="case-card-meta">
                                @if($study->client_name)<span class="case-card-client">{{ $study->client_name }}</span>@endif
                                @if($study->industry)<span class="case-card-industry">{{ $study->industry }}</span>@endif
                            </div>
                            <h3 class="case-card-title">
                                <a href="{{ route('case-studies.show', $study->slug) }}">{{ $study->title }}</a>
                            </h3>
                            @if($study->summary)
                                <p class="case-card-summary">{{ \Illuminate\Support\Str::limit($study->summary, 180) }}</p>
                            @endif
                            <a href="{{ route('case-studies.show', $study->slug) }}" class="case-card-link">
                                اقرأ الدراسة كاملة
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($caseStudies->hasPages())
                <div class="admin-pagination mt-10">
                    {{ $caseStudies->links() }}
                </div>
            @endif
        @endif
    </div>
</section>

{{-- ═══ ما الذي تحصل عليه في كل دراسة ═══ --}}
<section class="section-lg section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">ماذا ستجد في كل دراسة</p>
            <h2 class="heading-lg mb-4">ما الذي تحصل عليه <span class="text-gradient">في كل دراسة</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                دراساتنا ليست شهادات تسويقية. كل دراسة وثيقة عمل تستطيع الاستفادة منها وتطبيقها على مشاريع مشابهة.
            </p>
        </div>

        <div class="three-col">
            <article class="page-feature-card page-feature-primary reveal d-1">
                <div class="page-feature-bar" aria-hidden="true"></div>
                <span class="page-feature-index">01</span>
                <h3 class="page-feature-title">السياق والتحدي</h3>
                <p class="page-feature-body">من هو العميل، في أي قطاع، ما الموقف الأولي، وما المشكلة المحددة التي يحاول حلها.</p>
            </article>
            <article class="page-feature-card page-feature-teal reveal d-2">
                <div class="page-feature-bar" aria-hidden="true"></div>
                <span class="page-feature-index">02</span>
                <h3 class="page-feature-title">القرار الاستراتيجي</h3>
                <p class="page-feature-body">المنطق وراء كل قرار: لماذا اخترنا هذا المسار، ولماذا استبعدنا البدائل المتاحة الأخرى.</p>
            </article>
            <article class="page-feature-card page-feature-gold reveal d-3">
                <div class="page-feature-bar" aria-hidden="true"></div>
                <span class="page-feature-index">03</span>
                <h3 class="page-feature-title">الأرقام والتعلّمات</h3>
                <p class="page-feature-body">المؤشرات قبل وبعد، التحديات التي ظهرت أثناء التنفيذ، والدروس القابلة للاستفادة في مشاريع لاحقة.</p>
            </article>
        </div>
    </div>
</section>

{{-- ═══ CTA الختامي ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="cta-section reveal">
            <div class="cta-section-grid"></div>
            <div class="cta-section-glow"></div>
            <div class="cta-section-content">
                <div class="section-badge mb-5 internal-cta-badge">
                    <span class="section-badge-text internal-cta-badge-text">شاركنا قصتك</span>
                </div>
                <h2 class="cta-headline">هل لديك قصة نمو تستحق التوثيق؟</h2>
                <p class="cta-body">نختار سنوياً عدداً محدوداً من قصص النمو لتوثيقها كدراسات حالة بمشاركة كاملة من العميل.</p>
                <div class="cta-actions">
                    <a href="{{ route('contact') }}" class="btn btn-primary btn-xl">رشّح قصة مشروعك</a>
                    <a href="{{ route('paths.index') }}" class="btn btn-ghost btn-xl">استكشف المسارات</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('head')
<style>
    .cases-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: var(--sp-5);
    }
    .case-card {
        display: flex;
        flex-direction: column;
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        overflow: hidden;
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .case-card:hover {
        border-color: var(--p);
        transform: translateY(-3px);
    }
    .case-card-image {
        display: block;
        aspect-ratio: 16 / 9;
        overflow: hidden;
    }
    .case-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform var(--dur-slow) var(--ease);
    }
    .case-card:hover .case-card-image img {
        transform: scale(1.05);
    }
    .case-card-body {
        padding: var(--sp-5) var(--sp-6);
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    .case-card-meta {
        display: flex;
        gap: var(--sp-3);
        align-items: center;
        margin-bottom: var(--sp-3);
        flex-wrap: wrap;
    }
    .case-card-client {
        font-size: var(--fs-xs);
        font-weight: 800;
        color: var(--p);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .case-card-industry {
        font-size: var(--fs-xs);
        font-weight: 600;
        color: var(--text-muted);
        padding: 4px 10px;
        background: color-mix(in srgb, var(--p) 10%, transparent);
        border-radius: var(--r-full);
    }
    .case-card-title {
        font-size: var(--fs-lg);
        font-weight: 800;
        line-height: 1.4;
        margin-bottom: var(--sp-3);
    }
    .case-card-title a {
        color: var(--text);
        text-decoration: none;
        transition: color var(--dur-base) var(--ease);
    }
    .case-card-title a:hover { color: var(--p); }
    .case-card-summary {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.7;
        margin-bottom: var(--sp-4);
        flex: 1;
    }
    .case-card-link {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-2);
        font-size: var(--fs-sm);
        font-weight: 800;
        color: var(--p);
        text-decoration: none;
        margin-top: auto;
    }
    .case-card-link:hover { gap: var(--sp-3); }
</style>
@endpush
