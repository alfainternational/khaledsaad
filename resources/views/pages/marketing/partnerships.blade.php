@extends('layouts.marketing', ['title' => $title, 'description' => $description])

@section('content')
@php
    $partnershipTypes = [
        [
            'tone' => 'primary',
            'title' => 'شراكة المحتوى',
            'body' => 'منصات تعليمية، مجتمعات متخصصة، ومُنشئو محتوى في التسويق الاستراتيجي. تبادل محتوى، استضافات، دراسات مشتركة.',
            'fit' => 'مناسبة لـ: منصات أكاديمية، قنوات متخصصة، مجتمعات مهنية.',
        ],
        [
            'tone' => 'teal',
            'title' => 'شراكة التوزيع',
            'body' => 'وكالات وشركات استشارية تُقدّم المنصة لعملائها ضمن حزم خدماتها، مع نموذج عمولة واضح أو سعر مخفّض.',
            'fit' => 'مناسبة لـ: وكالات تسويق، مستشارين مستقلين، مسرّعات أعمال.',
        ],
        [
            'tone' => 'gold',
            'title' => 'شراكة تقنية',
            'body' => 'تكاملات مع أدوات تسويق، CRM، منصات تحليل. نبني جسوراً تخدم مستخدم كلا المنتجين.',
            'fit' => 'مناسبة لـ: منصات SaaS، أدوات تحليل، منصات CRM إقليمية.',
        ],
    ];

    $partnerBenefits = [
        ['title' => 'نمو متبادل', 'body' => 'قناة توزيع جديدة لكلا الطرفين، مع مواءمة في الجمهور المستهدف.'],
        ['title' => 'محتوى مشترك', 'body' => 'ورش، ويبينارات، دراسات حالة موثّقة تُعزّز سمعة الطرفين.'],
        ['title' => 'دعم فني مُخصص', 'body' => 'فريق تكامل داخلي للشركاء المعتمدين، مع قنوات دعم سريعة.'],
        ['title' => 'شفافية كاملة', 'body' => 'اتفاقية مكتوبة، مؤشرات قياس واضحة، ومراجعة ربعية للنتائج.'],
    ];

    $applicationSteps = [
        ['step' => '01', 'title' => 'تقديم الطلب', 'body' => 'عبّئ نموذج التواصل مع وصف شركتك ونوع الشراكة المقترحة.'],
        ['step' => '02', 'title' => 'جلسة تعارف', 'body' => 'اجتماع قصير لتوضيح الرؤية والتأكد من التوافق في الجمهور والأهداف.'],
        ['step' => '03', 'title' => 'اتفاقية مبدئية', 'body' => 'مسودة تصف الالتزامات، النسب، وخطة الإطلاق والقياس.'],
        ['step' => '04', 'title' => 'الإطلاق والمتابعة', 'body' => 'بدء التعاون الفعلي مع مراجعة ربعية للمؤشرات والتطوير.'],
    ];
@endphp

{{-- ═══ Hero ═══ --}}
<section class="section-lg internal-page-hero">
    <div class="site-container">
        <div class="section-header reveal max-w-3xl mx-auto">
            <div class="section-badge section-badge-center mb-4">
                <span class="section-dot"></span>
                <span class="section-badge-text">{{ $page?->subtitle ?? 'الشراكات' }}</span>
            </div>
            <h1 class="heading-lg mb-4">
                {{ $page?->title ?? 'نبني مع من يؤمن بالمنهجية' }} — <span class="text-gradient">لا مع من يبحث عن شعار</span>
            </h1>
            <p class="text-body-lg max-w-2xl mx-auto">
                شراكاتنا ليست شعارات على صفحة. هي علاقات تحريرية وتجارية مدروسة مع جهات تشارك فلسفتنا في جودة المحتوى ورصانة المنهج.
            </p>
            <div class="page-actions page-actions-center mt-6">
                <a href="{{ route('contact') }}" class="btn btn-primary btn-lg">قدّم طلب شراكة</a>
                <a href="#partnership-types" class="btn btn-secondary btn-lg">استكشف الأنواع</a>
            </div>

            @if($page?->body_html)
                <div class="mt-8">
                    @include('pages.marketing.partials.cms-body', ['html' => $page?->body_html])
                </div>
            @endif
        </div>
    </div>
</section>

{{-- ═══ أنواع الشراكات ═══ --}}
<section id="partnership-types" class="section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">أنواع الشراكات</p>
            <h2 class="heading-lg mb-4">ثلاث مسارات <span class="text-gradient">للعمل معاً</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                اختر المسار الأقرب إلى طبيعة عملك. كل مسار له نموذج تعاون محدد، ومؤشرات نجاح متفق عليها مسبقاً.
            </p>
        </div>

        <div class="three-col">
            @foreach($partnershipTypes as $i => $type)
            <article class="page-feature-card page-feature-{{ $type['tone'] }} reveal d-{{ $i + 1 }}">
                <div class="page-feature-bar" aria-hidden="true"></div>
                <span class="page-feature-index">{{ sprintf('%02d', $i + 1) }}</span>
                <h3 class="page-feature-title">{{ $type['title'] }}</h3>
                <p class="page-feature-body">{{ $type['body'] }}</p>
                <p class="partnership-fit">{{ $type['fit'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ لماذا الشراكة ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">ما الذي تكسبه</p>
            <h2 class="heading-lg mb-4">أربع فوائد <span class="text-gradient">من الشراكة معنا</span></h2>
        </div>

        <div class="four-col">
            @foreach($partnerBenefits as $i => $benefit)
            <article class="partner-benefit-card reveal d-{{ ($i % 3) + 1 }}">
                <span class="partner-benefit-index">{{ sprintf('%02d', $i + 1) }}</span>
                <h3 class="partner-benefit-title">{{ $benefit['title'] }}</h3>
                <p class="partner-benefit-body">{{ $benefit['body'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ شركاؤنا الحاليون ═══ --}}
@if(count($partners) > 0)
<section class="section-lg section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">شركاؤنا</p>
            <h2 class="heading-lg">من يعمل <span class="text-gradient">معنا اليوم</span></h2>
        </div>

        <div class="partners-grid">
            @foreach ($partners as $partner)
                <article class="partner-card reveal d-{{ ($loop->iteration % 3) + 1 }}">
                    @if($partner->logo_path)
                        <div class="partner-card-logo">
                            <img src="{{ asset('storage/'.$partner->logo_path) }}" alt="{{ $partner->name }}" loading="lazy">
                        </div>
                    @endif
                    <h3 class="partner-card-name">{{ $partner->name }}</h3>
                    @if($partner->description)
                        <p class="partner-card-desc">{{ $partner->description }}</p>
                    @endif
                    @if($partner->website_url)
                        <a href="{{ $partner->website_url }}" class="partner-card-link" target="_blank" rel="noopener noreferrer">
                            زر الموقع
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
                                <polyline points="15 3 21 3 21 9"/>
                                <line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                        </a>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ═══ خطوات التقديم ═══ --}}
<section class="section-lg {{ count($partners) > 0 ? '' : 'section-band bg-2' }}">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">مسار التقديم</p>
            <h2 class="heading-lg mb-4">أربع خطوات <span class="text-gradient">من الطلب إلى الإطلاق</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                عملية الشراكة واضحة ومُوثّقة. لا مفاوضات مُرهِقة ولا عقود فضفاضة — كل مرحلة لها مُخرج محدد.
            </p>
        </div>

        <div class="partnership-steps">
            @foreach($applicationSteps as $i => $step)
            <article class="partnership-step reveal d-{{ ($i % 3) + 1 }}">
                <span class="partnership-step-num">{{ $step['step'] }}</span>
                <div class="partnership-step-body">
                    <h3 class="partnership-step-title">{{ $step['title'] }}</h3>
                    <p class="partnership-step-desc">{{ $step['body'] }}</p>
                </div>
                @if(!$loop->last)
                    <span class="partnership-step-connector" aria-hidden="true"></span>
                @endif
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ CTA الختامي ═══ --}}
<section class="section-lg {{ count($partners) > 0 ? 'section-band bg-2' : '' }}">
    <div class="site-container">
        <div class="cta-section reveal">
            <div class="cta-section-grid"></div>
            <div class="cta-section-glow"></div>
            <div class="cta-section-content">
                <div class="section-badge mb-5 internal-cta-badge">
                    <span class="section-badge-text internal-cta-badge-text">ابدأ بخطوة</span>
                </div>
                <h2 class="cta-headline">لديك اقتراح شراكة؟ نستمع باهتمام</h2>
                <p class="cta-body">نختار شركاءنا بعناية لنحمي جودة المنصة. إذا كنت ترى توافقاً في الرؤية والجمهور، دعنا نتحدث.</p>
                <div class="cta-actions">
                    <a href="{{ route('contact') }}" class="btn btn-primary btn-xl">قدّم طلب الشراكة</a>
                    <a href="{{ route('about') }}" class="btn btn-ghost btn-xl">تعرّف علينا أولاً</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('head')
<style>
    /* — ملاءمة كل نوع شراكة — */
    .partnership-fit {
        margin-top: var(--sp-3);
        padding-top: var(--sp-3);
        border-top: 1px dashed var(--border);
        font-size: var(--fs-xs);
        color: var(--text-muted);
        line-height: 1.7;
        font-style: italic;
    }

    /* — فوائد الشراكة — */
    .partner-benefit-card {
        position: relative;
        padding: var(--sp-6);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .partner-benefit-card:hover {
        border-color: var(--p);
        transform: translateY(-3px);
    }
    .partner-benefit-index {
        display: inline-block;
        font-size: var(--fs-xs);
        font-weight: 900;
        color: var(--p);
        letter-spacing: 0.1em;
        margin-bottom: var(--sp-3);
    }
    .partner-benefit-title {
        font-size: var(--fs-md);
        font-weight: 800;
        color: var(--text);
        margin-bottom: var(--sp-2);
    }
    .partner-benefit-body {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.7;
        margin: 0;
    }

    /* — بطاقات الشركاء — */
    .partners-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: var(--sp-5);
    }
    .partner-card {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        padding: var(--sp-6);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface);
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .partner-card:hover {
        border-color: var(--p);
        transform: translateY(-3px);
    }
    .partner-card-logo {
        width: 100%;
        height: 64px;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        margin-bottom: var(--sp-4);
    }
    .partner-card-logo img {
        max-height: 56px;
        max-width: 160px;
        object-fit: contain;
    }
    .partner-card-name {
        font-size: var(--fs-md);
        font-weight: 800;
        color: var(--text);
        margin-bottom: var(--sp-2);
    }
    .partner-card-desc {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.7;
        margin-bottom: var(--sp-4);
        flex: 1;
    }
    .partner-card-link {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-2);
        font-size: var(--fs-sm);
        font-weight: 800;
        color: var(--p);
        text-decoration: none;
        margin-top: auto;
    }
    .partner-card-link:hover { gap: var(--sp-3); }

    /* — خطوات التقديم — */
    .partnership-steps {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: var(--sp-5);
        position: relative;
    }
    .partnership-step {
        position: relative;
        padding: var(--sp-6);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .partnership-step:hover {
        border-color: var(--p);
        transform: translateY(-3px);
    }
    .partnership-step-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: var(--r-md);
        background: linear-gradient(135deg, var(--p), var(--teal));
        color: #fff;
        font-weight: 900;
        font-size: var(--fs-sm);
        letter-spacing: 0.04em;
        margin-bottom: var(--sp-4);
    }
    .partnership-step-title {
        font-size: var(--fs-md);
        font-weight: 800;
        color: var(--text);
        margin-bottom: var(--sp-2);
    }
    .partnership-step-desc {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.7;
        margin: 0;
    }
    .partnership-step-connector {
        display: none;
    }
</style>
@endpush
