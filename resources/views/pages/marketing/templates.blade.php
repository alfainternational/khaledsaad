@extends('layouts.marketing', ['title' => $title, 'description' => $description])

@section('content')
@php
    $templateBenefits = [
        [
            'tone' => 'primary',
            'title' => 'قوالب جاهزة للتطبيق',
            'body' => 'كل قالب مبني على منهجية مُختبَرة، قابل للتعديل وفق سياق مشروعك بخطوات واضحة.',
        ],
        [
            'tone' => 'teal',
            'title' => 'توفير وقت التنفيذ',
            'body' => 'بدل البدء من الصفر، انطلق من هيكل مدروس واضبطه على مقاسك — اختصر الأسابيع إلى ساعات.',
        ],
        [
            'tone' => 'gold',
            'title' => 'توافق مع المنصة',
            'body' => 'القوالب متوافقة مع أدوات المنصة والاستوديو الذكي — تنتقل من قالب إلى تنفيذ بضغطة واحدة.',
        ],
    ];

    // تجميع القوالب حسب الفئة لعرضها في أقسام منطقية
    $groupedHighlights = collect($highlights)->groupBy(function ($item) {
        return $item->category ?: 'عام';
    });
@endphp

{{-- ═══ Hero ═══ --}}
<section class="section-lg internal-page-hero">
    <div class="site-container">
        <div class="section-header reveal max-w-3xl mx-auto">
            <div class="section-badge section-badge-center mb-4">
                <span class="section-dot"></span>
                <span class="section-badge-text">{{ $page?->subtitle ?? 'مكتبة القوالب' }}</span>
            </div>
            <h1 class="heading-lg mb-4">
                {{ $page?->title ?? 'قوالب تسريع التنفيذ' }} — <span class="text-gradient">من الفكرة إلى الجاهز</span>
            </h1>
            <p class="text-body-lg max-w-2xl mx-auto">
                مكتبة منظمة من القوالب الاستراتيجية والتسويقية الجاهزة: خطط، رسائل، نماذج عروض، وسكربتات — كلها مبنية على منهجية مُختبَرة ومنظّمة حسب مراحل المنصة الخمس.
            </p>

            <div class="page-actions page-actions-center mt-6">
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg">احصل على المكتبة كاملة</a>
                <a href="{{ route('studio.index') }}" class="btn btn-secondary btn-lg">جرّب الاستوديو</a>
            </div>

            @if($page?->body_html)
                <div class="mt-8">
                    @include('pages.marketing.partials.cms-body', ['html' => $page?->body_html])
                </div>
            @endif
        </div>
    </div>
</section>

{{-- ═══ لماذا قوالبنا ═══ --}}
<section class="section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">لماذا القوالب؟</p>
            <h2 class="heading-lg mb-4">ثلاث أسباب <span class="text-gradient">لاستخدام قوالبنا</span></h2>
        </div>

        <div class="three-col">
            @foreach($templateBenefits as $i => $benefit)
            <article class="page-feature-card page-feature-{{ $benefit['tone'] }} reveal d-{{ $i + 1 }}">
                <div class="page-feature-bar" aria-hidden="true"></div>
                <span class="page-feature-index">{{ sprintf('%02d', $i + 1) }}</span>
                <h3 class="page-feature-title">{{ $benefit['title'] }}</h3>
                <p class="page-feature-body">{{ $benefit['body'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ مكتبة القوالب ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">المكتبة</p>
            <h2 class="heading-lg mb-4">قوالب مصنّفة <span class="text-gradient">حسب المهمة</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                كل قالب موجه لمهمة محددة. اختر القالب المناسب لمرحلتك ومشروعك، وابدأ التنفيذ في دقائق.
            </p>
        </div>

        @if(count($highlights) === 0)
            <div class="page-summary-card text-center max-w-2xl mx-auto">
                <p class="text-body">سيتم إضافة القوالب الأولى قريباً.</p>
                <div class="mt-4">
                    <a href="{{ route('register') }}" class="btn btn-primary btn-lg">سجّل ليصلك الإشعار</a>
                </div>
            </div>
        @else
            @foreach($groupedHighlights as $category => $items)
                @if($groupedHighlights->count() > 1)
                    <h3 class="templates-category-title">{{ $category }}</h3>
                @endif
                <div class="templates-grid mb-10">
                    @foreach($items as $i => $item)
                    <article class="template-card reveal d-{{ ($i % 3) + 1 }}">
                        <div class="template-card-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="9" y1="13" x2="15" y2="13"/>
                                <line x1="9" y1="17" x2="15" y2="17"/>
                            </svg>
                        </div>
                        @if($item->category && $groupedHighlights->count() === 1)
                            <span class="template-card-category">{{ $item->category }}</span>
                        @endif
                        <h4 class="template-card-title">{{ $item->title }}</h4>
                        <p class="template-card-desc">{{ $item->description }}</p>
                        @if($item->body_html)
                            <div class="template-card-body">{!! $item->body_html !!}</div>
                        @endif
                        @if($item->cta_label && $item->cta_url)
                            <a href="{{ $item->cta_url }}" class="template-card-cta">
                                {{ $item->cta_label }}
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </a>
                        @endif
                    </article>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>
</section>

{{-- ═══ كيف نستخدم القوالب ═══ --}}
<section class="section-lg section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">طريقة الاستخدام</p>
            <h2 class="heading-lg mb-4">ثلاث خطوات <span class="text-gradient">للتطبيق الفوري</span></h2>
        </div>

        <div class="three-col">
            <article class="step-card reveal d-1">
                <span class="step-number">01</span>
                <h3 class="step-title">اختر القالب</h3>
                <p class="step-body">تصفّح المكتبة وحدد القالب الذي يناسب مهمتك الحالية ومرحلة مشروعك.</p>
                <div class="step-bottom-bar" aria-hidden="true"></div>
            </article>
            <article class="step-card reveal d-2">
                <span class="step-number">02</span>
                <h3 class="step-title">خصّص السياق</h3>
                <p class="step-body">اضبط القالب على معطيات مشروعك: الجمهور، العرض، القناة، الميزانية.</p>
                <div class="step-bottom-bar" aria-hidden="true"></div>
            </article>
            <article class="step-card reveal d-3">
                <span class="step-number">03</span>
                <h3 class="step-title">نفّذ واقس</h3>
                <p class="step-body">انشر المخرج، تابع المؤشرات، وعدّل القالب حسب النتائج الفعلية على أرض الواقع.</p>
                <div class="step-bottom-bar" aria-hidden="true"></div>
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
                    <span class="section-badge-text internal-cta-badge-text">ابدأ التنفيذ</span>
                </div>
                <h2 class="cta-headline">المكتبة الكاملة بانتظارك داخل المنصة</h2>
                <p class="cta-body">القوالب متاحة بصلاحيات موسعة داخل الحساب. ابدأ مجاناً واحصل على وصول فوري.</p>
                <div class="cta-actions">
                    <a href="{{ route('register') }}" class="btn btn-primary btn-xl">ابدأ مجاناً</a>
                    <a href="{{ route('pricing') }}" class="btn btn-ghost btn-xl">قارن الباقات</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('head')
<style>
    .templates-category-title {
        font-size: var(--fs-xl);
        font-weight: 800;
        color: var(--text);
        margin: var(--sp-8) 0 var(--sp-5);
        padding-bottom: var(--sp-3);
        border-bottom: 2px solid var(--p);
        display: inline-block;
    }

    .templates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: var(--sp-4);
    }
    .template-card {
        display: flex;
        flex-direction: column;
        padding: var(--sp-6);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .template-card:hover {
        border-color: var(--p);
        transform: translateY(-3px);
    }
    .template-card-icon {
        width: 44px; height: 44px;
        border-radius: var(--r-md);
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, color-mix(in srgb, var(--p) 18%, transparent), color-mix(in srgb, var(--teal) 18%, transparent));
        color: var(--p);
        margin-bottom: var(--sp-4);
    }
    .template-card-category {
        font-size: var(--fs-xs);
        font-weight: 800;
        color: var(--p);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: var(--sp-2);
    }
    .template-card-title {
        font-size: var(--fs-md);
        font-weight: 800;
        color: var(--text);
        margin-bottom: var(--sp-2);
        line-height: 1.4;
    }
    .template-card-desc {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.7;
        margin-bottom: var(--sp-3);
    }
    .template-card-body {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        margin-bottom: var(--sp-3);
    }
    .template-card-body ul { padding-inline-start: 1.25rem; }
    .template-card-cta {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-2);
        font-size: var(--fs-sm);
        font-weight: 800;
        color: var(--p);
        text-decoration: none;
        margin-top: auto;
    }
    .template-card-cta:hover { gap: var(--sp-3); }
</style>
@endpush
