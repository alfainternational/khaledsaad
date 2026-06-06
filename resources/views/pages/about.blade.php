@extends('layouts.marketing', ['title' => $title, 'description' => $description])

@section('content')
@php
    $phoneE164 = '+966533052074';
    $phoneDisplay = '+966 53 305 2074';
    $waUrl = 'https://wa.me/966533052074';
    $xUrl = 'https://x.com/KhaledAASaad';
    $linkedinUrl = 'https://www.linkedin.com/in/khaledaasaad/';

    // سنوات الخبرة تُحسب ديناميكياً من 2011 (بداية Social Media Assistant)
    $yearsExperience = (int) (now()->year - 2011);

    $focusPillars = [
        [
            'tone' => 'primary',
            'index' => '01',
            'title' => 'التسويق الاستراتيجي',
            'body' => 'تصميم منظومات تسويقية متكاملة تبدأ من تشخيص المشروع وتنتهي بخطة نمو قابلة للتنفيذ، مع ربط الرسالة والعرض والقناة والقياس في مسار واحد.',
        ],
        [
            'tone' => 'teal',
            'index' => '02',
            'title' => 'الحملات المدفوعة والأداء',
            'body' => 'إدارة حملات Google و Meta و LinkedIn بمنهجية قائمة على البيانات: تحسين ROI، تقليل الهدر الإعلاني، والتجويد المستمر استناداً إلى تحليلات حقيقية.',
        ],
        [
            'tone' => 'gold',
            'index' => '03',
            'title' => 'الذكاء الاصطناعي التسويقي',
            'body' => 'توظيف نماذج اللغة الكبيرة (LLMs) والأدوات التوليدية لتسريع التحليل، استخراج رؤى من البيانات، وتوليد مخرجات متسقة مع استراتيجية المشروع.',
        ],
    ];

    $services = [
        'الاستراتيجية التسويقية الشاملة',
        'إدارة الحملات المدفوعة (Meta / Google / TikTok)',
        'استراتيجية المحتوى والهوية التحريرية',
        'تسويق محركات البحث (SEO / SEM)',
        'بناء وقيادة فرق التسويق',
        'تخطيط الميزانية وتحسين ROI',
        'التسويق التعليمي والتسجيل الأكاديمي',
        'التسويق المدعوم بالذكاء الاصطناعي',
    ];

    $experiences = [
        [
            'role' => 'اختصاصي تسويق',
            'company' => 'شركة الشمال التعليمية للتعليم',
            'period' => 'نوفمبر 2024 — حتى الآن',
            'location' => 'المملكة العربية السعودية',
            'body' => 'قيادة استراتيجية التسويق التعليمي وحملات التسجيل الأكاديمي، مع توظيف الذكاء الاصطناعي في تحسين الأداء الإعلاني وجودة الاستهداف.',
            'tone' => 'primary',
        ],
        [
            'role' => 'مدير تسويق',
            'company' => 'شركة ألفا العالمية للأنشطة المتعددة المحدودة',
            'period' => 'يناير 2020 — فبراير 2025',
            'location' => 'السودان · عن بُعد',
            'body' => 'إدارة منظومة التسويق عبر أنشطة متعددة، وبناء خطط نشر استراتيجية قابلة للتوسع، وقيادة فرق عمل متعددة التخصصات.',
            'tone' => 'teal',
        ],
        [
            'role' => 'Marketing Manager',
            'company' => 'Hoopoespark',
            'period' => 'أكتوبر 2022 — نوفمبر 2024',
            'location' => 'الرياض، السعودية',
            'body' => 'تطوير وتنفيذ استراتيجيات التسويق الرقمي الشاملة، الإشراف على حملات Facebook و Instagram و Google، وقيادة فرق المحتوى والتحليل الرقمي، مع تقارير أداء دورية للإدارة.',
            'tone' => 'gold',
        ],
        [
            'role' => 'Digital Marketing Officer',
            'company' => 'Awrag Taiba',
            'period' => 'مارس 2016 — أغسطس 2022',
            'location' => 'المدينة المنورة، السعودية',
            'body' => 'تصميم وتنفيذ حملات تسويق رقمي متكاملة تجمع بين الإعلانات المدفوعة والمحتوى العضوي، والإشراف على فريق التسويق، وتحليل البيانات لتقديم توصيات أداء عملية.',
            'tone' => 'rose',
        ],
        [
            'role' => 'Marketing Supervisor',
            'company' => 'Design Lasteer Trading',
            'period' => 'فبراير 2013 — أبريل 2015',
            'location' => 'السودان',
            'body' => 'بناء استراتيجيات التسويق الرقمي بما فيها الحملات الإعلانية، SEO، والتسويق بالبريد الإلكتروني، والمشاركة في قرارات تخصيص الميزانية وتحسين العائد.',
            'tone' => 'violet',
        ],
        [
            'role' => 'Digital Marketing Trainee',
            'company' => 'KN Technology',
            'period' => 'فبراير 2012 — مارس 2014',
            'location' => 'السودان',
            'body' => 'المشاركة في تنفيذ حملات التسويق الرقمي، وتعلّم أساسيات التحليل والأداء، ودعم المحتوى العضوي على منصات التواصل الاجتماعي.',
            'tone' => 'teal',
        ],
        [
            'role' => 'Social Media Assistant',
            'company' => 'WAELCO Technology & Investment Ltd',
            'period' => 'أكتوبر 2011 — فبراير 2013',
            'location' => 'السودان',
            'body' => 'دعم فريق التسويق في إدارة حملات التواصل الاجتماعي، وتطوير المحتوى العضوي، وتعلّم أساسيات تحليل البيانات لتحسين الأداء.',
            'tone' => 'primary',
        ],
    ];

    $certifications = [
        // — الذكاء الاصطناعي ونماذج اللغة الكبيرة —
        [
            'title' => 'AI Fluency Framework & Foundations',
            'issuer' => 'Anthropic',
            'year' => 'أبريل 2026',
            'category' => 'ai',
        ],
        [
            'title' => 'Claude Code in Action',
            'issuer' => 'Anthropic',
            'year' => 'أبريل 2026',
            'category' => 'ai',
        ],
        [
            'title' => 'Introduction to Claude Cowork',
            'issuer' => 'Anthropic',
            'year' => 'أبريل 2026',
            'category' => 'ai',
        ],
        [
            'title' => 'Generative AI Essentials: Using LLMs to Work with Data',
            'issuer' => 'IBM',
            'year' => 'أبريل 2026',
            'category' => 'ai',
        ],
        [
            'title' => 'تصميم الطلبات لمهام العمل اليومية (Prompt Design)',
            'issuer' => 'Google',
            'year' => 'أكتوبر 2025',
            'category' => 'ai',
        ],

        // — التسويق الرقمي والأداء —
        [
            'title' => 'Fundamentals of Digital Marketing',
            'issuer' => 'Google Digital Garage',
            'year' => 'نوفمبر 2022',
            'category' => 'marketing',
        ],
        [
            'title' => 'Google Ads Search Certification',
            'issuer' => 'Google Skillshop',
            'year' => '2022',
            'category' => 'marketing',
        ],
        [
            'title' => 'Google Analytics Individual Qualification (GAIQ)',
            'issuer' => 'Google Skillshop',
            'year' => '2021',
            'category' => 'marketing',
        ],
        [
            'title' => 'Meta Blueprint — Digital Advertising',
            'issuer' => 'Meta (Facebook)',
            'year' => '2021',
            'category' => 'marketing',
        ],
        [
            'title' => 'HubSpot Inbound Marketing Certification',
            'issuer' => 'HubSpot Academy',
            'year' => '2020',
            'category' => 'marketing',
        ],
        [
            'title' => 'SEMrush SEO Fundamentals',
            'issuer' => 'SEMrush Academy',
            'year' => '2020',
            'category' => 'marketing',
        ],

        // — القيادة والإدارة والاستراتيجية —
        [
            'title' => 'McKinsey Forward Program',
            'issuer' => 'McKinsey & Company',
            'year' => 'ديسمبر 2024',
            'category' => 'leadership',
        ],
        [
            'title' => 'Project Management Principles',
            'issuer' => 'PMI — Project Management Institute',
            'year' => '2019',
            'category' => 'leadership',
        ],
        [
            'title' => 'Strategic Thinking for Managers',
            'issuer' => 'LinkedIn Learning',
            'year' => '2022',
            'category' => 'leadership',
        ],

        // — التطوير التقني —
        [
            'title' => 'بناء المواقع الإلكترونية باستخدام ووردبريس',
            'issuer' => 'إدراك — مؤسسة الملكة رانيا',
            'year' => '2017',
            'category' => 'tech',
        ],
        [
            'title' => 'HTML & CSS Fundamentals',
            'issuer' => 'إدراك',
            'year' => '2016',
            'category' => 'tech',
        ],
    ];

    $certCategories = [
        'ai'         => ['label' => 'الذكاء الاصطناعي', 'tone' => 'primary'],
        'marketing'  => ['label' => 'التسويق الرقمي والأداء', 'tone' => 'teal'],
        'leadership' => ['label' => 'القيادة والاستراتيجية', 'tone' => 'gold'],
        'tech'       => ['label' => 'التطوير التقني', 'tone' => 'rose'],
    ];

    $education = [
        [
            'tone' => 'primary',
            'degree' => 'بكالوريوس تقنية المعلومات',
            'school' => 'جامعة النيلين — كلية علوم الحاسوب وتقنية المعلومات',
            'location' => 'الخرطوم، السودان',
            'period' => '2006 — 2010',
            'meta' => 'تخصص نُظُم المعلومات',
            'body' => 'درجة البكالوريوس في تقنية المعلومات مع تركيز على نُظُم المعلومات الإدارية، قواعد البيانات، وتحليل النُظُم وتصميمها. المزج بين الخلفية التقنية وعمل التسويق لاحقاً أنتج منهجية تحليلية قائمة على البيانات في كل قرار تسويقي.',
            'highlights' => [
                'تحليل وتصميم نُظُم المعلومات',
                'قواعد البيانات وإدارة البيانات',
                'هندسة البرمجيات وتطوير الويب',
                'مبادئ أمن المعلومات',
            ],
        ],
        [
            'tone' => 'teal',
            'degree' => 'الثانوية العامة — القسم العلمي',
            'school' => 'المرحلة الثانوية',
            'location' => 'السودان',
            'period' => '2003 — 2006',
            'meta' => 'المسار العلمي — الرياضيات والعلوم',
            'body' => 'تأسيس أكاديمي في الرياضيات والعلوم الطبيعية، ساهم في بناء القدرة على التفكير المنطقي وحل المشكلات، وهي مهارة انعكست لاحقاً على المنهج التحليلي في التسويق الرقمي.',
            'highlights' => [
                'الرياضيات المتقدمة',
                'الفيزياء والكيمياء',
                'مهارات التفكير المنطقي',
            ],
        ],
    ];

    $continuingLearning = [
        'قراءة أكثر من 30 كتاباً سنوياً في التسويق والاستراتيجية والإدارة.',
        'متابعة دورية لأبحاث McKinsey و Harvard Business Review و MIT Sloan.',
        'حضور مؤتمرات وندوات متخصصة في التسويق الرقمي والذكاء الاصطناعي.',
        'تطبيق تجريبي مستمر للأدوات التسويقية الجديدة على مشاريع حقيقية.',
        'مشاركة التعلم عبر نشرة «يلا نفهم تسويق» ومنشورات لينكد إن.',
    ];

    $topSkills = [
        'المهارات التحليلية',
        'إدارة المشروعات',
        'الاستراتيجية',
        'Strategic Marketing',
        'قيادة فرق عمل',
        'Marketing Budget Management',
        'Google Analytics',
        'Client Relationship Management',
        'SEO / SEM',
        'Content Strategy',
        'Community Management',
        'Large Language Models (LLMs)',
        'AI Prompting Techniques',
        'Generative AI',
    ];

    $stats = [
        ['value' => $yearsExperience . '+', 'label' => 'عاماً في التسويق الرقمي'],
        ['value' => '7', 'label' => 'مناصب قيادية متدرجة'],
        ['value' => count($certifications).'+', 'label' => 'شهادات احترافية معتمدة'],
        ['value' => '3', 'label' => 'دول خبرة ميدانية'],
    ];

    $methodology = [
        [
            'num' => '01',
            'title' => 'تشخيص عميق',
            'body' => 'نبدأ من SWOT ومسح السوق والمنافسين حتى تعرف «أين أنت» قبل أن تحدد «إلى أين تتجه».',
        ],
        [
            'num' => '02',
            'title' => 'عميل في بؤرة التركيز',
            'body' => 'بناء Persona دقيقة تُبرز مخاوف العميل ودوافعه ومحفزات القرار الشرائي لديه.',
        ],
        [
            'num' => '03',
            'title' => 'عرض لا يُقاوَم',
            'body' => 'صياغة العرض والتسعير وسلّم القيمة بحيث يصبح القرار الطبيعي للعميل هو «نعم».',
        ],
        [
            'num' => '04',
            'title' => 'قمع يعمل يومياً',
            'body' => 'هندسة رحلة العميل من أول ظهور إعلاني حتى إتمام الصفقة، مع معالجة الاعتراضات بذكاء.',
        ],
        [
            'num' => '05',
            'title' => 'قياس وتوسّع',
            'body' => 'مؤشرات أداء واضحة، خطة تنفيذية 90 يوماً، وتوصيات ذكية لتوسيع ما ينجح وإيقاف ما لا ينجح.',
        ],
    ];
@endphp

{{-- ═══ Hero: المؤسس + بطاقة التعريف ═══ --}}
<section class="section-lg internal-page-hero">
    <div class="site-container">
        <div class="two-col-wide internal-page-layout">
            <div class="reveal-left">
                <div class="section-badge">
                    <span class="section-dot"></span>
                    <span class="section-badge-text">عن المؤسس</span>
                </div>

                <h1 class="heading-lg mb-4">خالد عبدالله سعد</h1>
                <p class="text-body-lg text-muted mb-2" lang="en">Khaled A. A. Saad</p>
                <p class="text-body-lg mb-6">
                    مدير تسويق ومستشار استراتيجي بخبرة تتجاوز {{ $yearsExperience }} عاماً في التسويق الرقمي، من تدريب ميداني في السودان إلى قيادة حملات تسويقية واسعة في المملكة العربية السعودية. مؤسس «منصة خالد سعد للنمو» التي تهندس رحلة التسويق من الفكرة إلى التنفيذ بمنهجية واضحة لا بالعشوائية.
                </p>

                <div class="page-actions mb-6">
                    <a href="{{ route('paths.index') }}" class="btn btn-primary btn-lg">استكشف المنصة</a>
                    <a href="{{ route('register') }}" class="btn btn-secondary btn-lg">ابدأ مجاناً</a>
                </div>

                <div class="page-inline-notes">
                    <div class="page-inline-note">
                        <span class="page-inline-note-label">الرؤية</span>
                        <p class="page-inline-note-body">
                            تحويل النمو من اجتهادات عشوائية إلى نظام مهندس، يجعل صاحب المشروع يرى السوق والعميل والمنافس بوضوح كامل قبل أن يصرف ريالاً واحداً.
                        </p>
                    </div>
                    <div class="page-inline-note">
                        <span class="page-inline-note-label">المنهج</span>
                        <p class="page-inline-note-body">
                            ربط الاستراتيجية بالتنفيذ عبر أدوات تشخيصية، واستوديو ذكي مدعوم بنماذج اللغة الكبيرة، يحوّل بيانات مشروعك إلى مخرجات جاهزة للعمل.
                        </p>
                    </div>
                </div>
            </div>

            <aside id="contact" class="page-summary-card reveal-right d-2" aria-labelledby="about-card-title">
                <div class="page-summary-glow" aria-hidden="true"></div>
                <div class="about-profile-head mb-6">
                    <div class="about-avatar" aria-hidden="true">خ</div>
                    <div>
                        <h2 id="about-card-title" class="text-lg font-bold mb-1">خالد سعد</h2>
                        <p class="text-sm text-muted m-0">مدير تسويق · مستشار استراتيجي · مؤسس المنصة</p>
                    </div>
                </div>

                <div class="page-summary-item mb-5">
                    <p class="page-summary-label">الموقع الحالي</p>
                    <p class="page-summary-body mb-0">
                        عرعر — الحدود الشمالية، المملكة العربية السعودية
                    </p>
                </div>

                <div class="page-summary-divider"></div>

                <div class="page-summary-item mb-5">
                    <p class="page-summary-label">تواصل مباشر</p>
                    <p class="page-summary-body mb-3">
                        للاستشارات المهنية والشراكات، التواصل عبر الجوال أو واتساب.
                    </p>
                    <div class="about-contact-row">
                        <a href="tel:{{ preg_replace('/\s+/', '', $phoneE164) }}" class="btn btn-secondary btn-sm about-contact-pill">{{ $phoneDisplay }}</a>
                        <a href="{{ $waUrl }}" class="btn btn-primary btn-sm about-contact-pill" target="_blank" rel="noopener noreferrer">واتساب</a>
                    </div>
                </div>

                <div class="page-summary-divider"></div>

                <div class="page-summary-item">
                    <p class="page-summary-label">التواجد المهني</p>
                    <div class="about-social-grid">
                        <a href="{{ $linkedinUrl }}" class="about-social-link" target="_blank" rel="noopener noreferrer">
                            <span class="about-social-icon" aria-hidden="true">
                                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z M4 6a2 2 0 100-4 2 2 0 000 4z"/></svg>
                            </span>
                            <span>لينكد إن</span>
                        </a>
                        <a href="{{ $xUrl }}" class="about-social-link" target="_blank" rel="noopener noreferrer">
                            <span class="about-social-icon" aria-hidden="true">
                                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg>
                            </span>
                            <span>إكس (X)</span>
                        </a>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

{{-- ═══ إحصاءات المسيرة ═══ --}}
<section class="section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">أرقام تختصر المسيرة</p>
            <h2 class="heading-lg mb-4">مسار <span class="text-gradient">مُختبَر ميدانياً</span> لا نظرية من كتاب</h2>
            <p class="text-body max-w-2xl mx-auto">
                كل ما تراه في المنصة خضع لاختبار حقيقي في السوق قبل أن يصل إليك: حملات بميزانيات فعلية، وفرق عمل قادها خالد بنفسه، ومشاريع متعددة في قطاعات متباينة.
            </p>
        </div>

        <div class="four-col">
            @foreach($stats as $i => $stat)
            <article class="metric-card reveal d-{{ $i + 1 }}">
                <div class="metric-top-bar" aria-hidden="true"></div>
                <p class="metric-value">{{ $stat['value'] }}</p>
                <p class="metric-label">{{ $stat['label'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ محاور التخصص ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">محاور التخصص</p>
            <h2 class="heading-lg mb-4">ثلاثة محاور تصنع <span class="text-gradient">الفرق التنفيذي</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                المسيرة لم تكن متفرقة بين مهارات؛ بل تراكمت حول ثلاثة محاور أساسية يُكمل بعضها بعضاً في أي مشروع نمو جاد.
            </p>
        </div>

        <div class="three-col">
            @foreach($focusPillars as $i => $pillar)
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

{{-- ═══ الرحلة المهنية (Timeline) ═══ --}}
<section class="section-lg section-band">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">الرحلة المهنية</p>
            <h2 class="heading-lg mb-4">من متدرّب تسويق رقمي إلى <span class="text-gradient">قائد منظومات نمو</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                سبع محطات صنعت الرؤية الحالية: من التعامل المباشر مع حملات السوشيال في بداياته، إلى قيادة استراتيجيات تسويق شاملة في قطاعي التعليم والتجارة.
            </p>
        </div>

        <div class="about-timeline">
            @foreach($experiences as $i => $exp)
            <article class="about-timeline-item path-tone-{{ $exp['tone'] }} reveal d-{{ ($i % 3) + 1 }}">
                <div class="about-timeline-marker" aria-hidden="true"></div>
                <div class="about-timeline-body">
                    <p class="about-timeline-period">{{ $exp['period'] }}</p>
                    <h3 class="about-timeline-role">{{ $exp['role'] }}</h3>
                    <p class="about-timeline-company">{{ $exp['company'] }} <span class="about-timeline-sep">·</span> {{ $exp['location'] }}</p>
                    <p class="about-timeline-desc">{{ $exp['body'] }}</p>
                </div>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ المنهجية الخماسية ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">المنهجية</p>
            <h2 class="heading-lg mb-4">خمس مراحل تحوّل <span class="text-gradient">الضبابية إلى خطة</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                المنصة تختصر سنوات الخبرة في مسار واضح يمشي فيه صاحب المشروع خطوة بخطوة، بدل التنقل بين أدوات متفرقة لا تعرف سياق بعضها البعض.
            </p>
        </div>

        <div class="about-method-grid">
            @foreach($methodology as $i => $step)
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

{{-- ═══ الخدمات والمهارات ═══ --}}
<section class="section-lg section-band bg-2">
    <div class="site-container">
        <div class="two-col-wide about-col-start">
            <div class="reveal-left">
                <p class="text-eyebrow mb-3 text-p">الخدمات المهنية</p>
                <h2 class="heading-lg mb-4">ماذا يقدّم خالد <span class="text-gradient">للمشاريع الجادة؟</span></h2>
                <p class="text-body mb-6">
                    بخلاف المنصة، يقدّم خالد استشارات مباشرة ومشاريع تشغيلية لأصحاب المشاريع الذين يحتاجون إلى خبرة ميدانية في بناء وقيادة منظومات التسويق.
                </p>

                <ul class="about-services-list" role="list">
                    @foreach($services as $service)
                    <li class="about-services-item">
                        <span class="about-services-dot" aria-hidden="true"></span>
                        <span>{{ $service }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>

            <div class="page-summary-card reveal-right d-2">
                <div class="page-summary-glow" aria-hidden="true"></div>
                <p class="page-summary-label mb-4">أبرز المهارات التقنية والقيادية</p>
                <div class="about-skills-cloud">
                    @foreach($topSkills as $skill)
                    <span class="about-skill-chip">{{ $skill }}</span>
                    @endforeach
                </div>

                <div class="page-summary-divider"></div>

                <p class="page-summary-label mb-3">لمحة أكاديمية</p>
                <p class="about-academic-teaser">
                    بكالوريوس تقنية المعلومات من جامعة النيلين — تفاصيل التعليم والشهادات في الأقسام التالية.
                </p>
            </div>
        </div>
    </div>
</section>

{{-- ═══ التعليم الأكاديمي (قسم موسّع) ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">التعليم الأكاديمي</p>
            <h2 class="heading-lg mb-4">أساس أكاديمي <span class="text-gradient">مختلط بين التقنية والتسويق</span></h2>
            <p class="text-body max-w-2xl mx-auto">
                الخلفية الأكاديمية في تقنية المعلومات شكّلت الطريقة التي أتعامل بها مع التسويق: منظومة مترابطة، وقرارات قائمة على البيانات، ومنهجية تحليلية في كل خطوة.
            </p>
        </div>

        <div class="about-education-grid">
            @foreach($education as $i => $edu)
            <article class="about-edu-card path-tone-{{ $edu['tone'] }} reveal d-{{ ($i % 3) + 1 }}">
                <div class="about-edu-top-bar" aria-hidden="true"></div>
                <div class="about-edu-header">
                    <p class="about-edu-period">{{ $edu['period'] }}</p>
                    <h3 class="about-edu-degree">{{ $edu['degree'] }}</h3>
                    <p class="about-edu-school">{{ $edu['school'] }}</p>
                    <p class="about-edu-location">
                        <span>{{ $edu['location'] }}</span>
                        @if(!empty($edu['meta']))
                            <span class="about-edu-sep">·</span>
                            <span>{{ $edu['meta'] }}</span>
                        @endif
                    </p>
                </div>
                <p class="about-edu-body">{{ $edu['body'] }}</p>
                @if(!empty($edu['highlights']))
                <div class="about-edu-highlights">
                    <p class="about-edu-highlights-label">أبرز المجالات</p>
                    <ul class="about-edu-highlights-list" role="list">
                        @foreach($edu['highlights'] as $h)
                        <li><span class="about-edu-dot" aria-hidden="true"></span><span>{{ $h }}</span></li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </article>
            @endforeach
        </div>

        <div class="about-continuing-card reveal mt-8">
            <div class="about-continuing-head">
                <p class="text-eyebrow text-p mb-2">التعلم المستمر</p>
                <h3 class="about-continuing-title">التعليم لا يتوقف عند الشهادة</h3>
                <p class="about-continuing-body">
                    الشهادات الأكاديمية نقطة بداية لا نهاية. القيمة الفعلية تتراكم من الممارسة الميدانية والتعلم المستمر الذي لا يتوقف.
                </p>
            </div>
            <ul class="about-continuing-list" role="list">
                @foreach($continuingLearning as $item)
                <li class="about-continuing-item">
                    <span class="about-continuing-dot" aria-hidden="true"></span>
                    <span>{{ $item }}</span>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

{{-- ═══ الشهادات الاحترافية (مجمّعة حسب المحور) ═══ --}}
<section class="section-lg section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">التعلّم المستمر</p>
            <h2 class="heading-lg mb-4">شهادات من <span class="text-gradient">أكبر بيوت الخبرة</span> العالمية</h2>
            <p class="text-body max-w-2xl mx-auto">
                الشهادات تفتح الأبواب، والمعرفة تُبقيك حاضراً، والتعلم المستمر يجعلك في المقدمة. هذه ليست شعاراً بل ممارسة يومية موثّقة عبر أكثر من {{ count($certifications) }} شهادة احترافية موزّعة على أربعة محاور.
            </p>
        </div>

        @php
            $groupedCerts = collect($certifications)->groupBy('category');
        @endphp

        @foreach($certCategories as $catKey => $catMeta)
            @if($groupedCerts->has($catKey))
            @php $items = $groupedCerts->get($catKey); @endphp
            <div class="about-cert-group reveal">
                <div class="about-cert-group-head path-tone-{{ $catMeta['tone'] }}">
                    <span class="about-cert-group-bar" aria-hidden="true"></span>
                    <div>
                        <p class="about-cert-group-label">المحور</p>
                        <h3 class="about-cert-group-title">{{ $catMeta['label'] }}</h3>
                    </div>
                    <span class="about-cert-group-count">{{ $items->count() }} شهادات</span>
                </div>
                <div class="about-certs-grid">
                    @foreach($items as $i => $cert)
                    <article class="about-cert-card path-tone-{{ $catMeta['tone'] }} reveal d-{{ ($i % 3) + 1 }}">
                        <div class="about-cert-badge" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15a5 5 0 100-10 5 5 0 000 10z"/><path d="M8.21 13.89L7 23l5-3 5 3-1.21-9.12"/></svg>
                        </div>
                        <div class="about-cert-body">
                            <h4 class="about-cert-title">{{ $cert['title'] }}</h4>
                            <p class="about-cert-issuer">{{ $cert['issuer'] }}@if(!empty($cert['year'])) <span class="about-cert-sep">·</span> {{ $cert['year'] }}@endif</p>
                        </div>
                    </article>
                    @endforeach
                </div>
            </div>
            @endif
        @endforeach
    </div>
</section>

{{-- ═══ النشرة والمحتوى ═══ --}}
<section class="section-lg section-band">
    <div class="site-container">
        <div class="two-col-wide about-col-start">
            <div class="reveal-left">
                <p class="text-eyebrow mb-3 text-p">المحتوى والمجتمع</p>
                <h2 class="heading-lg mb-4">«يلا نفهم تسويق» — <span class="text-gradient">نشرة أسبوعية</span></h2>
                <p class="text-body mb-4">
                    نشرة دورية يكتبها خالد بنفسه على لينكد إن: شرح مبسط وعملي للتسويق، مبني على تجارب ميدانية حقيقية لا على نظريات منسوخة لا تعرف سياق السوق العربي.
                </p>
                <p class="text-body mb-6">
                    إلى جانب النشرة، يشارك خالد منشورات مهنية على لينكد إن وإكس حول الاستراتيجية والنمو والذكاء الاصطناعي التسويقي.
                </p>

                <ul class="about-bullet-list">
                    <li><strong>لينكد إن:</strong> مقالات أعمق، تحليلات مهنية، وإبراز للخبرة في بيئة العمل.</li>
                    <li><strong>إكس (X):</strong> أفكار سريعة، نقاشات، وإشارات إلى أدوات ومنهجية المنصة.</li>
                </ul>
            </div>

            <div class="page-summary-card reveal-right d-2">
                <div class="page-summary-glow" aria-hidden="true"></div>
                <p class="page-summary-label">روابط سريعة</p>
                <div class="about-link-stack">
                    <a href="{{ $linkedinUrl }}" class="about-large-link" target="_blank" rel="noopener noreferrer">
                        <span>الملف الكامل على لينكد إن</span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                    <a href="{{ $xUrl }}" class="about-large-link" target="_blank" rel="noopener noreferrer">
                        <span>الملف على إكس</span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                    <a href="{{ $waUrl }}" class="about-large-link" target="_blank" rel="noopener noreferrer">
                        <span>مراسلة عبر واتساب</span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                </div>
            </div>
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
                    <span class="section-badge-text internal-cta-badge-text">الخطوة التالية</span>
                </div>
                <h2 class="cta-headline">ابدأ رحلة نموّك على منهجية مختبرة</h2>
                <p class="cta-body">اختر المسار الأنسب لمرحلتك، أو تواصل مباشرة لمناقشة استشارة مخصصة لمشروعك.</p>
                <div class="cta-actions">
                    <a href="{{ route('paths.index') }}" class="btn btn-primary btn-xl">استكشف المسارات</a>
                    <a href="{{ $waUrl }}" class="btn btn-ghost btn-xl" target="_blank" rel="noopener noreferrer">تواصل مباشر</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('head')
<style>
    /* — محاذاة الأعمدة من الأعلى في أقسام محددة — */
    .about-col-start { align-items: flex-start !important; }

    /* — رأس البطاقة الجانبية — */
    .about-profile-head { display: flex; align-items: center; gap: var(--sp-4); }
    .about-avatar {
        width: 64px; height: 64px; border-radius: var(--r-xl);
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.35rem;
        background: linear-gradient(135deg, var(--p), var(--teal));
        color: #fff;
        flex-shrink: 0;
        box-shadow: var(--shadow-md);
    }
    .about-contact-row { display: flex; flex-wrap: wrap; gap: var(--sp-3); }
    .about-contact-pill { white-space: nowrap; }

    /* — روابط التواصل الاجتماعي — */
    .about-social-grid { display: grid; gap: var(--sp-3); }
    .about-social-link {
        display: flex; align-items: center; gap: var(--sp-3);
        padding: var(--sp-3) var(--sp-4);
        border-radius: var(--r-md);
        border: 1px solid var(--border);
        background: var(--surface-3);
        color: var(--text);
        font-weight: 700; font-size: var(--fs-sm);
        text-decoration: none;
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .about-social-link:hover { border-color: var(--border-2); transform: translateY(-2px); }
    .about-social-icon { display: flex; color: var(--p); }

    /* — الخط الزمني للخبرات — */
    .about-timeline {
        position: relative;
        display: grid;
        gap: var(--sp-5);
        margin-top: var(--sp-10);
    }
    .about-timeline::before {
        content: '';
        position: absolute;
        top: 0; bottom: 0;
        inset-inline-start: 14px;
        width: 2px;
        background: linear-gradient(180deg, var(--border-2), transparent);
    }
    .about-timeline-item {
        position: relative;
        padding: var(--sp-5) var(--sp-6);
        padding-inline-start: var(--sp-12);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .about-timeline-item:hover {
        border-color: var(--tone, var(--border-2));
        transform: translateY(-2px);
    }
    .about-timeline-marker {
        position: absolute;
        inset-inline-start: 6px;
        top: var(--sp-6);
        width: 18px; height: 18px;
        border-radius: 50%;
        background: var(--surface-1);
        border: 3px solid var(--tone, var(--p));
        box-shadow: 0 0 0 4px var(--surface-2);
    }
    .about-timeline-period {
        font-size: var(--fs-xs);
        font-weight: 700;
        color: var(--tone, var(--p));
        margin-bottom: var(--sp-2);
        letter-spacing: 0.02em;
    }
    .about-timeline-role {
        font-size: var(--fs-lg);
        font-weight: 800;
        color: var(--text);
        margin-bottom: var(--sp-1);
        line-height: 1.3;
    }
    .about-timeline-company {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        margin-bottom: var(--sp-3);
        font-weight: 600;
    }
    .about-timeline-sep { opacity: 0.5; margin: 0 var(--sp-1); }
    .about-timeline-desc {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.8;
        margin: 0;
    }

    /* — شبكة المنهجية الخماسية — */
    .about-method-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: var(--sp-4);
    }

    /* — قائمة الخدمات — */
    .about-services-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        gap: var(--sp-3);
    }
    .about-services-item {
        display: flex;
        align-items: flex-start;
        gap: var(--sp-3);
        padding: var(--sp-3) var(--sp-4);
        border-radius: var(--r-md);
        background: var(--surface-3);
        border: 1px solid var(--border);
        color: var(--text);
        font-weight: 600;
        font-size: var(--fs-sm);
        line-height: 1.7;
    }
    .about-services-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--p), var(--teal));
        margin-top: 8px;
        flex-shrink: 0;
    }

    /* — سحابة المهارات — */
    .about-skills-cloud {
        display: flex;
        flex-wrap: wrap;
        gap: var(--sp-2);
        position: relative;
        z-index: 1;
    }
    .about-skill-chip {
        padding: 6px 14px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--surface-3);
        color: var(--text);
        font-size: var(--fs-xs);
        font-weight: 600;
        transition: border-color var(--dur-base) var(--ease), color var(--dur-base) var(--ease);
    }
    .about-skill-chip:hover {
        border-color: var(--p);
        color: var(--p);
    }

    /* — تعريف اللمحة الأكاديمية في البطاقة الجانبية — */
    .about-academic-teaser {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.7;
        margin: 0;
        position: relative;
        z-index: 1;
    }

    /* — قسم التعليم الموسّع — */
    .about-education-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: var(--sp-5);
        margin-top: var(--sp-8);
    }
    .about-edu-card {
        position: relative;
        padding: var(--sp-7) var(--sp-6) var(--sp-6);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        overflow: hidden;
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .about-edu-card:hover {
        border-color: var(--tone, var(--border-2));
        transform: translateY(-3px);
    }
    .about-edu-top-bar {
        position: absolute;
        top: 0; inset-inline-start: 0; inset-inline-end: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--tone, var(--p)), color-mix(in srgb, var(--tone, var(--p)) 40%, transparent));
    }
    .about-edu-header { margin-bottom: var(--sp-4); }
    .about-edu-period {
        font-size: var(--fs-xs);
        font-weight: 800;
        color: var(--tone, var(--p));
        letter-spacing: 0.04em;
        text-transform: uppercase;
        margin-bottom: var(--sp-2);
    }
    .about-edu-degree {
        font-size: var(--fs-xl);
        font-weight: 900;
        color: var(--text);
        line-height: 1.3;
        margin-bottom: var(--sp-2);
    }
    .about-edu-school {
        font-size: var(--fs-sm);
        font-weight: 700;
        color: var(--text);
        margin-bottom: var(--sp-1);
    }
    .about-edu-location {
        font-size: var(--fs-xs);
        color: var(--text-muted);
        font-weight: 600;
        margin: 0;
    }
    .about-edu-sep { opacity: 0.5; margin: 0 var(--sp-1); }
    .about-edu-body {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.9;
        margin-bottom: var(--sp-5);
    }
    .about-edu-highlights {
        padding-top: var(--sp-4);
        border-top: 1px dashed var(--border);
    }
    .about-edu-highlights-label {
        font-size: var(--fs-xs);
        font-weight: 800;
        color: var(--tone, var(--p));
        letter-spacing: 0.04em;
        margin-bottom: var(--sp-3);
    }
    .about-edu-highlights-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        gap: var(--sp-2);
    }
    .about-edu-highlights-list li {
        display: flex;
        align-items: flex-start;
        gap: var(--sp-2);
        font-size: var(--fs-sm);
        color: var(--text);
        font-weight: 600;
        line-height: 1.6;
    }
    .about-edu-dot {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: var(--tone, var(--p));
        margin-top: 8px;
        flex-shrink: 0;
    }

    /* — بطاقة التعلم المستمر — */
    .about-continuing-card {
        padding: var(--sp-8);
        border-radius: var(--r-xl);
        border: 1px solid var(--border);
        background: linear-gradient(135deg, color-mix(in srgb, var(--p) 4%, var(--surface-2)), var(--surface-2));
        display: grid;
        grid-template-columns: 1fr 1.2fr;
        gap: var(--sp-8);
        align-items: start;
    }
    .about-continuing-head { }
    .about-continuing-title {
        font-size: var(--fs-xl);
        font-weight: 900;
        color: var(--text);
        margin-bottom: var(--sp-3);
        line-height: 1.3;
    }
    .about-continuing-body {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.8;
        margin: 0;
    }
    .about-continuing-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        gap: var(--sp-3);
    }
    .about-continuing-item {
        display: flex;
        align-items: flex-start;
        gap: var(--sp-3);
        padding: var(--sp-3) var(--sp-4);
        border-radius: var(--r-md);
        background: var(--surface-3);
        border: 1px solid var(--border);
        font-size: var(--fs-sm);
        color: var(--text);
        font-weight: 600;
        line-height: 1.7;
    }
    .about-continuing-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--p), var(--teal));
        margin-top: 8px;
        flex-shrink: 0;
    }

    /* — مجموعات الشهادات حسب المحور — */
    .about-cert-group {
        margin-bottom: var(--sp-10);
    }
    .about-cert-group:last-child { margin-bottom: 0; }
    .about-cert-group-head {
        display: flex;
        align-items: center;
        gap: var(--sp-4);
        padding: var(--sp-4) var(--sp-5);
        border-radius: var(--r-lg);
        background: var(--surface-2);
        border: 1px solid var(--border);
        margin-bottom: var(--sp-5);
    }
    .about-cert-group-bar {
        width: 4px;
        height: 38px;
        border-radius: var(--r-sm);
        background: var(--tone, var(--p));
        flex-shrink: 0;
    }
    .about-cert-group-label {
        font-size: var(--fs-xs);
        font-weight: 700;
        color: var(--text-muted);
        letter-spacing: 0.04em;
        text-transform: uppercase;
        margin: 0 0 2px 0;
    }
    .about-cert-group-title {
        font-size: var(--fs-lg);
        font-weight: 800;
        color: var(--text);
        margin: 0;
    }
    .about-cert-group-count {
        margin-inline-start: auto;
        font-size: var(--fs-xs);
        font-weight: 800;
        color: var(--tone, var(--p));
        padding: 6px 14px;
        border-radius: var(--r-full);
        background: color-mix(in srgb, var(--tone, var(--p)) 10%, transparent);
        white-space: nowrap;
    }

    /* — بطاقات الشهادات — */
    .about-certs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: var(--sp-4);
    }
    .about-cert-card {
        display: flex;
        align-items: flex-start;
        gap: var(--sp-4);
        padding: var(--sp-5) var(--sp-6);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        transition: border-color var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
    }
    .about-cert-card:hover {
        border-color: var(--border-2);
        transform: translateY(-3px);
    }
    .about-cert-badge {
        width: 44px; height: 44px;
        border-radius: var(--r-md);
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, color-mix(in srgb, var(--p) 20%, transparent), color-mix(in srgb, var(--teal) 20%, transparent));
        color: var(--p);
        flex-shrink: 0;
    }
    .about-cert-body { flex: 1; min-width: 0; }
    .about-cert-title {
        font-size: var(--fs-base);
        font-weight: 800;
        color: var(--text);
        margin-bottom: var(--sp-2);
        line-height: 1.4;
    }
    .about-cert-issuer {
        font-size: var(--fs-xs);
        color: var(--text-muted);
        font-weight: 600;
        margin: 0;
    }
    .about-cert-sep { opacity: 0.5; margin: 0 var(--sp-1); }

    /* — قوائم وروابط عامة — */
    .about-bullet-list { margin: 0; padding-inline-start: 1.25rem; color: var(--text-muted); line-height: 1.9; }
    .about-bullet-list li { margin-bottom: var(--sp-3); }
    .about-link-stack { display: grid; gap: var(--sp-3); position: relative; z-index: 1; }
    .about-large-link {
        display: flex; align-items: center; justify-content: space-between; gap: var(--sp-3);
        padding: var(--sp-4) var(--sp-5);
        border-radius: var(--r-lg);
        border: 1px solid var(--border);
        background: var(--surface-3);
        color: var(--text);
        font-weight: 700;
        text-decoration: none;
        transition: border-color var(--dur-base) var(--ease), box-shadow var(--dur-base) var(--ease);
    }
    .about-large-link:hover { border-color: var(--border-2); box-shadow: var(--shadow-sm); }

    /* — تجاوب مع الشاشات الصغيرة — */
    @media (max-width: 768px) {
        .about-timeline-item {
            padding-inline-start: var(--sp-10);
        }
        .about-cert-card {
            padding: var(--sp-4) var(--sp-5);
        }
        .about-cert-title { font-size: var(--fs-sm); }
    }
</style>
@endpush
