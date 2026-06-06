@extends('layouts.marketing', ['title' => $title, 'description' => $description])

@section('content')
@php
    $studioPillars = [
        [
            'tone' => 'primary',
            'index' => '01',
            'title' => 'يقرأ سياقك أولاً',
            'body' => 'الاستوديو لا يبدأ من ورقة بيضاء. يستدعي بيانات مشروعك المحفوظة في الأدوات (الجملة التعريفية، العميل المثالي، العرض، التسعير) ويبني عليها مباشرة.',
        ],
        [
            'tone' => 'teal',
            'index' => '02',
            'title' => 'مخرجات قابلة للتنفيذ',
            'body' => 'إعلانات سوشيال، تسلسلات إيميل، رسائل واتساب، عناوين صفحات هبوط، سكربتات عرض — مخرجات جاهزة للنسخ والنشر، لا مسودات تحتاج إعادة صياغة.',
        ],
        [
            'tone' => 'gold',
            'index' => '03',
            'title' => 'تحت ضوابط ميزانيتك',
            'body' => 'كل قالب له تكلفة Credits معروفة مسبقاً، ورصيدك واضح في كل لحظة. تتحكم في الإنفاق قبل أن تبدأ، لا بعد فوات الأوان.',
        ],
    ];

    $studioWorkflow = [
        [
            'num' => '01',
            'title' => 'جهّز سياق مشروعك',
            'body' => 'استكمل الأدوات الأساسية في المراحل الخمس حتى يحصل الاستوديو على المادة الخام: الجمهور، العرض، الرسالة، القناة.',
        ],
        [
            'num' => '02',
            'title' => 'اختر القالب المناسب',
            'body' => 'مكتبة قوالب موجهة حسب المهمة: حملة إطلاق، تسلسل بريدي، صفحة هبوط، رسائل متابعة. كل قالب يخدم هدفاً محدداً.',
        ],
        [
            'num' => '03',
            'title' => 'راجع وعدّل واعتمد',
            'body' => 'المخرجات تظهر في الواجهة قابلة للتعديل المباشر، التصدير بصيغ متعددة (Markdown, HTML, PDF)، أو الحفظ ضمن ملف المشروع.',
        ],
        [
            'num' => '04',
            'title' => 'كرّر وحسّن',
            'body' => 'كل توليد محفوظ في سجلك. قارن النسخ، حسّن الـ Inputs، وكرر حتى تصل إلى المخرج الأمثل لمشروعك.',
        ],
    ];

    $studioTemplates = [
        ['title' => 'حملة سوشيال متكاملة', 'desc' => 'منشورات + كابشن + هاشتاقات لأسبوع كامل من المحتوى المتسق.'],
        ['title' => 'تسلسل بريدي للترحيب', 'desc' => 'سلسلة 5 رسائل تبني العلاقة من أول اشتراك حتى أول عملية شراء.'],
        ['title' => 'سكربت عرض مبيعات', 'desc' => 'هيكل مكالمة احترافي مع معالجة الاعتراضات الشائعة في قطاعك.'],
        ['title' => 'رسائل متابعة واتساب', 'desc' => 'قوالب احترافية للعملاء الباردين، الفاترين، والمترددين.'],
        ['title' => 'عناوين صفحات هبوط', 'desc' => 'بدائل متعددة لعنوان رئيسي مقنع مبني على عرضك الفعلي.'],
        ['title' => 'خطة محتوى شهرية', 'desc' => 'تقويم محتوى مدروس حسب أهداف مشروعك ومنصاتك المستهدفة.'],
    ];

    $studioStats = [
        ['value' => '100+', 'label' => 'قالب جاهز للاستخدام'],
        ['value' => '6', 'label' => 'صيغ تصدير مدعومة'],
        ['value' => '5', 'label' => 'مراحل سياق متكاملة'],
        ['value' => '24/7', 'label' => 'متاح بدون توقف'],
    ];
@endphp

{{-- ═══ Hero ═══ --}}
<section class="section-lg internal-page-hero">
    <div class="site-container">
        <div class="two-col-wide internal-page-layout">
            <div class="reveal-left">
                <div class="section-badge">
                    <span class="section-dot"></span>
                    <span class="section-badge-text">{{ $page?->subtitle ?? 'الاستوديو الذكي' }}</span>
                </div>

                <h1 class="heading-lg mb-4">
                    {{ $page?->title ?? 'الاستوديو الذكي' }} —
                    <span class="text-gradient">من سياق مشروعك إلى مخرجات جاهزة</span>
                </h1>
                <p class="text-body-lg mb-6">
                    استوديو توليدي مدعوم بنماذج اللغة الكبيرة، يقرأ بيانات مشروعك المحفوظة في الأدوات ويحوّلها إلى مخرجات تسويقية متسقة مع رسالتك وعميلك ومرحلتك. لا تبدأ من الصفر، ولا تكرر شرح من أنت في كل مرة.
                </p>

                <div class="page-actions mb-6">
                    <a href="{{ route('register') }}" class="btn btn-primary btn-lg">جرّب الاستوديو</a>
                    <a href="{{ route('paths.index') }}" class="btn btn-secondary btn-lg">استكشف المسارات</a>
                </div>

                @if($page?->body_html)
                    <div class="marketing-cms-body text-body">
                        @include('pages.marketing.partials.cms-body', ['html' => $page?->body_html])
                    </div>
                @endif
            </div>

            <aside class="page-summary-card reveal-right d-2" aria-labelledby="studio-card-title">
                <div class="page-summary-glow" aria-hidden="true"></div>
                <h2 id="studio-card-title" class="text-lg font-bold mb-4">ماذا يقدّم الاستوديو؟</h2>

                <div class="page-summary-item mb-5">
                    <p class="page-summary-label">قوالب موجهة</p>
                    <p class="page-summary-body mb-0">
                        مكتبة تحديثها مستمر بقوالب تخدم كل مرحلة من مراحل المنصة الخمس.
                    </p>
                </div>

                <div class="page-summary-divider"></div>

                <div class="page-summary-item mb-5">
                    <p class="page-summary-label">سياق ذكي</p>
                    <p class="page-summary-body mb-0">
                        كل توليد يقرأ من Workspace Data: عرضك، عميلك، تسعيرك، خطتك التسويقية.
                    </p>
                </div>

                <div class="page-summary-divider"></div>

                <div class="page-summary-item">
                    <p class="page-summary-label">سجل قابل للمراجعة</p>
                    <p class="page-summary-body mb-0">
                        كل توليد محفوظ ومُصنّف، يمكن مراجعته، تعديله، أو إعادة استخدامه لاحقاً.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</section>

{{-- ═══ إحصاءات الاستوديو ═══ --}}
<section class="section-band bg-2">
    <div class="site-container">
        <div class="four-col">
            @foreach($studioStats as $i => $stat)
            <article class="metric-card reveal d-{{ $i + 1 }}">
                <div class="metric-top-bar" aria-hidden="true"></div>
                <p class="metric-value">{{ $stat['value'] }}</p>
                <p class="metric-label">{{ $stat['label'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ ثلاثة محاور تميّز الاستوديو ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">لماذا هذا الاستوديو؟</p>
            <h2 class="heading-lg mb-4">ثلاثة فروق <span class="text-gradient">تجعله أداة جادة</span> لا لعبة توليد</h2>
            <p class="text-body max-w-2xl mx-auto">
                الفرق بين أداة توليد عامة وأداة تسويقية حقيقية يكمن في فهم السياق. الاستوديو يبدأ من حيث ينتهي تشخيص مشروعك.
            </p>
        </div>

        <div class="three-col">
            @foreach($studioPillars as $i => $pillar)
            <article class="page-feature-card page-feature-{{ $pillar['tone'] }} reveal d-{{ $i + 1 }}">
                <div class="page-feature-bar" aria-hidden="true"></div>
                <span class="page-feature-index">{{ $pillar['index'] }}</span>
                <h3 class="page-feature-title">{{ $pillar['title'] }}</h3>
                <p class="page-feature-body">{{ $pillar['body'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ سير العمل (Workflow) ═══ --}}
<section class="section-lg section-band">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">كيف يعمل الاستوديو؟</p>
            <h2 class="heading-lg mb-4">أربع خطوات <span class="text-gradient">من الفكرة إلى المخرج النهائي</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                لا منحنى تعلّم معقد. الاستوديو يأخذك خطوة بخطوة من تجهيز السياق إلى التوليد، التعديل، ثم التصدير أو الاعتماد.
            </p>
        </div>

        <div class="studio-workflow-grid">
            @foreach($studioWorkflow as $i => $step)
            <article class="step-card reveal d-{{ ($i % 3) + 1 }}">
                <span class="step-number">{{ $step['num'] }}</span>
                <h3 class="step-title">{{ $step['title'] }}</h3>
                <p class="step-body">{{ $step['body'] }}</p>
                <div class="step-bottom-bar" aria-hidden="true"></div>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ مكتبة القوالب ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">مكتبة القوالب</p>
            <h2 class="heading-lg mb-4">قوالب لكل مهمة <span class="text-gradient">في رحلة التسويق</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                مكتبة في توسع مستمر. هذه عيّنة من القوالب الأكثر طلباً داخل الاستوديو، مرتبطة بمراحل المنصة الخمس.
            </p>
        </div>

        <div class="studio-templates-grid">
            @foreach($studioTemplates as $i => $tpl)
            <article class="studio-template-card reveal d-{{ ($i % 3) + 1 }}">
                <div class="studio-template-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>
                </div>
                <h3 class="studio-template-title">{{ $tpl['title'] }}</h3>
                <p class="studio-template-desc">{{ $tpl['desc'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ CTA الختامي ═══ --}}
<section class="section-lg section-band bg-2">
    <div class="site-container">
        <div class="cta-section reveal">
            <div class="cta-section-grid"></div>
            <div class="cta-section-glow"></div>
            <div class="cta-section-content">
                <div class="section-badge mb-5 internal-cta-badge">
                    <span class="section-badge-text internal-cta-badge-text">الخطوة التالية</span>
                </div>
                <h2 class="cta-headline">جرّب الاستوديو على مشروعك الفعلي</h2>
                <p class="cta-body">سجّل مجاناً، جهّز سياق مشروعك في الأدوات الأساسية، ثم انتقل إلى الاستوديو لتوليد أول مخرج.</p>
                <div class="cta-actions">
                    <a href="{{ route('register') }}" class="btn btn-primary btn-xl">ابدأ مجاناً</a>
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-xl">تسجيل الدخول</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('head')
<style>
    .marketing-cms-body h3 { font-size: var(--fs-lg); font-weight: 800; margin-top: 1.25rem; }
    .marketing-cms-body ul { padding-inline-start: 1.25rem; }

    /* — شبكة سير العمل — */
    .studio-workflow-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: var(--sp-4);
    }

    /* — شبكة القوالب — */
    .studio-templates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: var(--sp-4);
    }
    .studio-template-card {
        padding: var(--sp-6);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .studio-template-card:hover {
        border-color: var(--p);
        transform: translateY(-3px);
    }
    .studio-template-icon {
        width: 44px; height: 44px;
        border-radius: var(--r-md);
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, color-mix(in srgb, var(--p) 18%, transparent), color-mix(in srgb, var(--teal) 18%, transparent));
        color: var(--p);
        margin-bottom: var(--sp-4);
    }
    .studio-template-title {
        font-size: var(--fs-md);
        font-weight: 800;
        color: var(--text);
        margin-bottom: var(--sp-2);
        line-height: 1.4;
    }
    .studio-template-desc {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.7;
        margin: 0;
    }
</style>
@endpush
