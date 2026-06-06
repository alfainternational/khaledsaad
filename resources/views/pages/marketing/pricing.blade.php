@extends('layouts.marketing', ['title' => $title, 'description' => $description])

@section('content')
@php
    // قاموس ترجمة مفاتيح entitlements الفنية إلى نصوص بشرية
    $featureLabels = [
        'workspaces.max' => 'بيئات عمل (Workspaces)',
        'projects.max_per_workspace' => 'مشاريع لكل بيئة',
        'members.max_per_workspace' => 'أعضاء لكل بيئة',
        'modules.stage_1' => 'المرحلة 1 — اكتشف مشروعك',
        'modules.stage_2' => 'المرحلة 2 — ابنِ أساسك التسويقي',
        'modules.stage_3' => 'المرحلة 3 — ابنِ عرضك',
        'modules.stage_4' => 'المرحلة 4 — اجذب وحوّل',
        'modules.stage_5' => 'المرحلة 5 — قِس ووسّع',
        'modules.ai_studio' => 'الاستوديو الذكي (AI)',
        'modules.agency_mode' => 'وضع الوكالة (Multi-Client)',
        'ai_studio.monthly_credits' => 'رصيد توليد AI شهري',
        'white_label' => 'العلامة الخاصة (White Label)',
        'api_access' => 'وصول API عام',
        'priority_support' => 'دعم ذو أولوية',
        'sso' => 'تسجيل الدخول الموحّد (SSO)',
        'custom_integrations' => 'تكاملات مخصصة',
    ];

    $valueLabels = function ($val) {
        if (is_bool($val)) {
            return $val ? 'مُتاح' : 'غير مُتاح';
        }
        if (is_numeric($val)) {
            return number_format((float) $val, 0);
        }
        if ($val === 'unlimited' || $val === -1) {
            return 'غير محدود';
        }
        return (string) $val;
    };

    $tones = ['primary', 'teal', 'gold', 'rose', 'violet'];

    $faqs = [
        [
            'q' => 'هل يمكنني التبديل بين الباقات لاحقاً؟',
            'a' => 'نعم. الترقية أو التخفيض متاح في أي وقت من إعدادات الحساب، والتسوية تتم تلقائياً وفق المتبقي من الفترة الحالية.',
        ],
        [
            'q' => 'ماذا تعني «Credits» في الاستوديو الذكي؟',
            'a' => 'كل عملية توليد داخل الاستوديو تستهلك رصيداً (Credits) حسب نوع القالب وحجم المخرج. الباقات تشمل رصيداً شهرياً قابلاً للتجديد.',
        ],
        [
            'q' => 'هل بياناتي معزولة عن مشاريع أخرى؟',
            'a' => 'كل بيئة عمل (Workspace) معزولة بالكامل. لا تتسرب البيانات بين الـ Workspaces، حتى لو كانت تحت نفس الحساب.',
        ],
        [
            'q' => 'هل توجد فترة تجريبية؟',
            'a' => 'الباقة المجانية متاحة دائماً وتمنحك تجربة فعلية للأدوات الأساسية. الترقية تتم فقط عند الحاجة لميزات أوسع.',
        ],
        [
            'q' => 'كيف تعمل وضع الوكالة (Agency Mode)؟',
            'a' => 'يتيح لك إدارة عدة عملاء (Clients) داخل بيئة وكالة واحدة، مع عزل بيانات كل عميل وإمكانية مراجعة واعتماد المخرجات.',
        ],
        [
            'q' => 'ماذا يحدث عند تجاوز حد الاستخدام؟',
            'a' => 'تصلك تنبيهات ذكية قبل بلوغ الحد. عند التجاوز، يمكنك الترقية فوراً أو الانتظار حتى تجديد الفترة، دون فقدان أي بيانات.',
        ],
    ];
@endphp

{{-- ═══ Hero ═══ --}}
<section class="section-lg internal-page-hero">
    <div class="site-container">
        <div class="section-header reveal max-w-3xl mx-auto">
            <div class="section-badge section-badge-center mb-4">
                <span class="section-dot"></span>
                <span class="section-badge-text">{{ $page?->subtitle ?? 'الباقات والأسعار' }}</span>
            </div>
            <h1 class="heading-lg mb-4">
                {{ $page?->title ?? 'باقات تنمو' }} <span class="text-gradient">مع مرحلة مشروعك</span>
            </h1>
            <p class="text-body-lg max-w-2xl mx-auto">
                ادفع عندما تريد أكثر من مجرد تشخيص أولي: ملف مشروع حيّ، أدوات مترابطة، مخرجات AI أدق، وتقارير تساعدك على اتخاذ قرار واضح.
            </p>
        </div>

        @if($page?->body_html)
            <div class="max-w-3xl mx-auto mt-8">
                @include('pages.marketing.partials.cms-body', ['html' => $page?->body_html])
            </div>
        @endif
    </div>
</section>

{{-- ═══ بطاقات الباقات ═══ --}}
<section class="section-lg">
    <div class="site-container">
        @if($plans->isEmpty())
            <div class="page-summary-card text-center max-w-2xl mx-auto">
                <p class="text-body">لم تُفعَّل باقات بعد. تتم إدارة الباقات من لوحة الإدارة.</p>
            </div>
        @else
            <div class="pricing-plans-grid">
                @foreach ($plans as $i => $plan)
                    @php
                        $tone = $tones[$i % count($tones)];
                        $isFeatured = (int) $loop->iteration === 2 || $plan->code === 'pro';
                        $features = is_array($plan->features_json) ? $plan->features_json : [];
                    @endphp
                    <article class="pricing-plan-card path-tone-{{ $tone }} reveal d-{{ ($i % 3) + 1 }} {{ $isFeatured ? 'pricing-plan-featured' : '' }}">
                        @if($isFeatured)
                            <div class="pricing-plan-ribbon">الأكثر طلباً</div>
                        @endif

                        <div class="pricing-plan-head">
                            <p class="pricing-plan-eyebrow">{{ $plan->code }}</p>
                            <h2 class="pricing-plan-name">{{ $plan->name_ar ?? $plan->name_en ?? $plan->code }}</h2>
                            <div class="pricing-plan-price">
                                <span class="pricing-plan-amount">{{ number_format((float) $plan->monthly_price, 0) }}</span>
                                <span class="pricing-plan-currency">ر.س</span>
                                <span class="pricing-plan-period">/ شهرياً</span>
                            </div>
                            @if($plan->annual_price && (float) $plan->annual_price > 0)
                                <p class="pricing-plan-annual">
                                    أو <strong>{{ number_format((float) $plan->annual_price, 0) }} ر.س</strong> سنوياً
                                </p>
                            @endif
                        </div>

                        @if(!empty($features))
                            <div class="pricing-plan-divider"></div>
                            <ul class="pricing-features-list" role="list">
                                @foreach (array_slice($features, 0, 10, true) as $key => $val)
                                    @php $isAvailable = !is_bool($val) || $val === true; @endphp
                                    <li class="pricing-feature-item {{ $isAvailable ? '' : 'pricing-feature-disabled' }}">
                                        <span class="pricing-feature-icon" aria-hidden="true">
                                            @if($isAvailable)
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            @else
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            @endif
                                        </span>
                                        <span class="pricing-feature-label">
                                            <strong>{{ $featureLabels[$key] ?? $key }}</strong>
                                            @if(!is_bool($val))
                                                <span class="pricing-feature-value">{{ $valueLabels($val) }}</span>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="pricing-plan-cta">
                            <a href="{{ route('register') }}" class="btn {{ $isFeatured ? 'btn-primary' : 'btn-secondary' }} btn-lg w-full">
                                @if((float) $plan->monthly_price === 0.0)
                                    ابدأ مجاناً
                                @else
                                    اختر هذه الباقة
                                @endif
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- ═══ مقارنة بصرية مختصرة ═══ --}}
<section class="section-lg section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">القيمة قبل السعر</p>
            <h2 class="heading-lg mb-4">ماذا تحصل عليه <span class="text-gradient">مع كل باقة</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                كل باقة مصممة لمرحلة معينة من نمو المشروع. ابدأ بما يناسب مرحلتك الحالية، وارتقِ عند الحاجة دون تعطيل عملك.
            </p>
        </div>

        <div class="three-col">
            <article class="page-feature-card page-feature-primary reveal d-1">
                <div class="page-feature-bar" aria-hidden="true"></div>
                <span class="page-feature-index">A</span>
                <h3 class="page-feature-title">Clarify</h3>
                <p class="page-feature-body">ابدأ بتشخيص أولي ومعرفة أين المشكلة فعلاً، ثم ابنِ أول نسخة من ملف مشروعك التسويقي.</p>
            </article>
            <article class="page-feature-card page-feature-teal reveal d-2">
                <div class="page-feature-bar" aria-hidden="true"></div>
                <span class="page-feature-index">B</span>
                <h3 class="page-feature-title">Build</h3>
                <p class="page-feature-body">حوّل الملف إلى عرض ورسائل وخطة ومخرجات تشغيلية تعيش داخل المشروع بدل أن تتكرر في كل جلسة.</p>
            </article>
            <article class="page-feature-card page-feature-gold reveal d-3">
                <div class="page-feature-bar" aria-hidden="true"></div>
                <span class="page-feature-index">C</span>
                <h3 class="page-feature-title">Execute</h3>
                <p class="page-feature-body">فعّل الاستوديو والتقارير وإدارة الفريق أو العملاء عندما تريد تنفيذًا أسرع وتسليمات أهدأ ومراجعة أوضح.</p>
            </article>
        </div>
    </div>
</section>

{{-- ═══ أسئلة شائعة ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">الأسئلة الشائعة</p>
            <h2 class="heading-lg mb-4">إجابات <span class="text-gradient">قبل أن تسأل</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                ما يسأله معظم العملاء قبل التسجيل. إن لم تجد إجابتك هنا، تواصل معنا مباشرة وسنرد خلال يوم عمل.
            </p>
        </div>

        <div class="pricing-faq-grid">
            @foreach($faqs as $i => $faq)
            <details class="pricing-faq-item reveal d-{{ ($i % 3) + 1 }}">
                <summary class="pricing-faq-q">
                    <span>{{ $faq['q'] }}</span>
                    <span class="pricing-faq-icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </span>
                </summary>
                <p class="pricing-faq-a">{{ $faq['a'] }}</p>
            </details>
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
                    <span class="section-badge-text internal-cta-badge-text">جاهز للبداية؟</span>
                </div>
                <h2 class="cta-headline">ابدأ بالباقة المجانية اليوم</h2>
                <p class="cta-body">ابدأ بالتشخيص الأولي وبناء ملف مشروعك. عندما ترى القيمة الفعلية في العرض والخطة والـAI، انتقل إلى الباقة التي تناسبك.</p>
                <div class="cta-actions">
                    <a href="{{ route('register') }}" class="btn btn-primary btn-xl">ابدأ ببناء ملف مشروعك</a>
                    <a href="{{ route('tools.index') }}" class="btn btn-ghost btn-xl">احصل على تشخيص أولي</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('head')
<style>
    /* — شبكة الباقات — */
    .pricing-plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: var(--sp-5);
        align-items: stretch;
    }
    .pricing-plan-card {
        position: relative;
        display: flex;
        flex-direction: column;
        padding: var(--sp-8);
        border-radius: var(--r-xl);
        border: 1px solid var(--border);
        background: var(--surface-2);
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease), box-shadow var(--dur-base) var(--ease);
    }
    .pricing-plan-card:hover {
        border-color: var(--tone, var(--p));
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
    }
    .pricing-plan-featured {
        border-color: var(--tone, var(--p));
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--tone, var(--p)) 25%, transparent);
    }
    .pricing-plan-ribbon {
        position: absolute;
        top: -14px;
        inset-inline-end: var(--sp-6);
        background: linear-gradient(135deg, var(--tone, var(--p)), var(--p2));
        color: #fff;
        padding: 6px 16px;
        border-radius: var(--r-full);
        font-size: var(--fs-xs);
        font-weight: 800;
        box-shadow: var(--shadow-sm);
    }
    .pricing-plan-head { margin-bottom: var(--sp-5); }
    .pricing-plan-eyebrow {
        font-size: var(--fs-xs);
        font-weight: 700;
        color: var(--tone, var(--p));
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: var(--sp-2);
    }
    .pricing-plan-name {
        font-size: var(--fs-2xl);
        font-weight: 900;
        color: var(--text);
        margin-bottom: var(--sp-4);
        line-height: 1.2;
    }
    .pricing-plan-price {
        display: flex;
        align-items: baseline;
        gap: var(--sp-1);
        margin-bottom: var(--sp-2);
    }
    .pricing-plan-amount {
        font-size: var(--fs-5xl);
        font-weight: 900;
        color: var(--text);
        line-height: 1;
    }
    .pricing-plan-currency {
        font-size: var(--fs-md);
        font-weight: 700;
        color: var(--text-muted);
    }
    .pricing-plan-period {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        margin-inline-start: var(--sp-2);
    }
    .pricing-plan-annual {
        font-size: var(--fs-xs);
        color: var(--text-muted);
        margin: var(--sp-2) 0 0;
    }
    .pricing-plan-divider {
        height: 1px;
        background: var(--border);
        margin: var(--sp-4) 0;
    }
    .pricing-features-list {
        list-style: none;
        padding: 0;
        margin: 0 0 var(--sp-6);
        display: grid;
        gap: var(--sp-3);
        flex: 1;
    }
    .pricing-feature-item {
        display: flex;
        align-items: flex-start;
        gap: var(--sp-3);
        font-size: var(--fs-sm);
        line-height: 1.6;
    }
    .pricing-feature-icon {
        flex-shrink: 0;
        width: 22px; height: 22px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--tone, var(--p)) 14%, transparent);
        color: var(--tone, var(--p));
        margin-top: 2px;
    }
    .pricing-feature-disabled .pricing-feature-icon {
        background: color-mix(in srgb, var(--text-muted) 14%, transparent);
        color: var(--text-muted);
    }
    .pricing-feature-disabled .pricing-feature-label {
        color: var(--text-muted);
        opacity: 0.7;
    }
    .pricing-feature-label {
        flex: 1;
        color: var(--text);
        font-weight: 600;
    }
    .pricing-feature-value {
        margin-inline-start: var(--sp-2);
        color: var(--text-muted);
        font-weight: 500;
    }
    .pricing-plan-cta {
        margin-top: auto;
    }

    /* — أسئلة شائعة — */
    .pricing-faq-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: var(--sp-3);
        max-width: 920px;
        margin: 0 auto;
    }
    .pricing-faq-item {
        border: 1px solid var(--border);
        border-radius: var(--r-md);
        background: var(--surface-2);
        padding: var(--sp-4) var(--sp-5);
        transition: border-color var(--dur-base) var(--ease);
    }
    .pricing-faq-item[open] {
        border-color: var(--p);
        background: var(--surface);
    }
    .pricing-faq-q {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--sp-3);
        cursor: pointer;
        font-weight: 700;
        font-size: var(--fs-base);
        color: var(--text);
        list-style: none;
    }
    .pricing-faq-q::-webkit-details-marker { display: none; }
    .pricing-faq-icon {
        display: flex;
        color: var(--p);
        transition: transform var(--dur-base) var(--ease);
    }
    .pricing-faq-item[open] .pricing-faq-icon {
        transform: rotate(180deg);
    }
    .pricing-faq-a {
        margin: var(--sp-3) 0 0;
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.8;
    }

    @media (max-width: 768px) {
        .pricing-plan-card { padding: var(--sp-6); }
        .pricing-plan-amount { font-size: var(--fs-4xl); }
    }
</style>
@endpush
