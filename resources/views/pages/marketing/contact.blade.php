@extends('layouts.marketing', ['title' => $title, 'description' => $description])

@section('content')
@php
    $contactChannels = [
        [
            'tone' => 'primary',
            'title' => 'الدعم الفني',
            'body' => 'أسئلة حول الاستخدام، الحساب، الفوترة، أو مشاكل تقنية داخل المنصة.',
            'meta' => 'الرد خلال 24 ساعة عمل',
        ],
        [
            'tone' => 'teal',
            'title' => 'استشارة مشروع',
            'body' => 'مناقشة تحدي مشروعك قبل الاشتراك، أو طلب إرشاد اختيار الباقة المناسبة.',
            'meta' => 'جلسة تعريفية 30 دقيقة',
        ],
        [
            'tone' => 'gold',
            'title' => 'شراكات وتعاون',
            'body' => 'اقتراح تعاون، شراكة محتوى، برنامج إحالة، أو فرصة عمل داخل الفريق.',
            'meta' => 'التواصل عبر صفحة الشراكات',
        ],
    ];

    $contactFaqs = [
        [
            'q' => 'كم تستغرق الإجابة على رسالتي؟',
            'a' => 'نتعامل مع كل رسالة فردياً. الرسائل التقنية تحصل على رد خلال 24 ساعة عمل. الرسائل الاستراتيجية قد تحتاج 48 ساعة لإعداد رد مُفصّل.',
        ],
        [
            'q' => 'هل تقدمون استشارات خارج المنصة؟',
            'a' => 'المنصة نفسها هي طريقتنا الأساسية في تقديم القيمة. الاستشارات المباشرة متاحة بشكل محدود لباقات Team وما فوق.',
        ],
        [
            'q' => 'أين أرسل بلاغاً أمنياً أو حول الخصوصية؟',
            'a' => 'استخدم نفس النموذج مع عنوان الموضوع: "تقرير أمني". سنتعامل معه بأولوية عالية وسرية تامة.',
        ],
        [
            'q' => 'هل الرد من فريق حقيقي أم بوت؟',
            'a' => 'كل رد تتلقاه مكتوب يدوياً من عضو في الفريق. لا نستخدم ردوداً آلية على رسائلك.',
        ],
    ];

    $consultationChannels = [
        'الموقع الإلكتروني',
        'إنستغرام',
        'سناب شات',
        'تيك توك',
        'إعلانات Meta',
        'إعلانات Google',
        'واتساب',
        'بريد إلكتروني',
        'SEO / محتوى',
    ];

    $consultationServices = [
        'تشخيص المشروع',
        'التمركز والرسائل',
        'بناء العرض',
        'الخطة التسويقية',
        'خطة المحتوى',
        'الحملات والإعلانات',
        'المتابعة والتحويل',
        'تقارير وقرارات تنفيذية',
    ];

    $budgetRanges = [
        'أقل من 5,000 ريال',
        '5,000 - 15,000 ريال',
        '15,000 - 30,000 ريال',
        '30,000 - 75,000 ريال',
        'أكثر من 75,000 ريال',
        'غير محددة بعد',
    ];

    $activeMessageType = old('message_type', 'consultation');
@endphp

{{-- ═══ Hero ═══ --}}
<section class="section-lg internal-page-hero">
    <div class="site-container">
        <div class="section-header reveal max-w-3xl mx-auto">
            <div class="section-badge section-badge-center mb-4">
                <span class="section-dot"></span>
                <span class="section-badge-text">{{ $page?->subtitle ?? 'تواصل معنا' }}</span>
            </div>
            <h1 class="heading-lg mb-4">
                {{ $page?->title ?? 'تحدّث معنا' }} — <span class="text-gradient">قناة مباشرة بلا وسطاء</span>
            </h1>
            <p class="text-body-lg max-w-2xl mx-auto">
                سؤال، اقتراح، بلاغ، أو طلب استشارة — اكتب لنا وسيصلك رد شخصي من فريق المنصة. نقرأ كل رسالة ونرد عليها يدوياً.
            </p>
        </div>
    </div>
</section>

{{-- ═══ قنوات التواصل ═══ --}}
<section class="section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">قنوات التواصل</p>
            <h2 class="heading-lg mb-4">ثلاث قنوات <span class="text-gradient">حسب نوع طلبك</span></h2>
        </div>

        <div class="three-col">
            @foreach($contactChannels as $i => $channel)
            <article class="page-feature-card page-feature-{{ $channel['tone'] }} reveal d-{{ $i + 1 }}">
                <div class="page-feature-bar" aria-hidden="true"></div>
                <span class="page-feature-index">{{ sprintf('%02d', $i + 1) }}</span>
                <h3 class="page-feature-title">{{ $channel['title'] }}</h3>
                <p class="page-feature-body">{{ $channel['body'] }}</p>
                <p class="contact-channel-meta">{{ $channel['meta'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ النموذج + محتوى CMS ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="two-col-wide internal-page-layout">
            <div class="reveal-left">
                <p class="text-eyebrow mb-3 text-p">اكتب لنا</p>
                <h2 class="heading-lg mb-5">نصلك بالجواب <span class="text-gradient">الذي تحتاجه</span></h2>
                <p class="text-body mb-6">
                    أخبرنا بوضوح عن طلبك. كلما كانت رسالتك محددة، استطعنا إعداد رد أعمق وأسرع. إذا كان طلبك يخص مشروعاً قائماً، اذكر الرابط أو السياق.
                </p>

                @if($page?->body_html)
                    <div class="mt-6">
                        @include('pages.marketing.partials.cms-body', ['html' => $page?->body_html])
                    </div>
                @endif

                <div class="contact-info-blocks mt-8">
                    <div class="contact-info-block">
                        <div class="contact-info-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </div>
                        <div>
                            <p class="contact-info-label">البريد الإلكتروني</p>
                            <p class="contact-info-value">الرد خلال 24 ساعة عمل</p>
                        </div>
                    </div>
                    <div class="contact-info-block">
                        <div class="contact-info-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <div>
                            <p class="contact-info-label">أوقات العمل</p>
                            <p class="contact-info-value">الأحد — الخميس · 9ص — 6م</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-summary-card reveal-right d-2">
                <div class="page-summary-glow" aria-hidden="true"></div>
                <h3 class="contact-form-title">ابدأ من المسار المناسب</h3>
                <p class="contact-form-subtitle">رسالة سريعة أو ملف استشارة أولي يحوّل طلبك إلى ملخّص واضح قابل للمراجعة والتنفيذ.</p>

                @if (session('status'))
                    <div class="app-alert success mb-4" role="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="app-alert danger mb-4" role="alert">
                        <p class="font-bold mb-1">تحقق من البيانات التالية:</p>
                        <ul class="list-disc pr-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('contact.store') }}" class="contact-form" id="contact-intake-form">
                    @csrf
                    <div class="contact-mode-switch" role="radiogroup" aria-label="نوع الطلب">
                        <label class="contact-mode-option">
                            <input type="radio" name="message_type" value="consultation" @checked($activeMessageType === 'consultation')>
                            <span>استشارة مشروع</span>
                        </label>
                        <label class="contact-mode-option">
                            <input type="radio" name="message_type" value="general" @checked($activeMessageType === 'general')>
                            <span>رسالة عامة</span>
                        </label>
                    </div>

                    <label class="contact-form-field">
                        <span class="contact-form-label">الاسم الكامل</span>
                        <input class="admin-input" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="كما تحب أن ننادِيك">
                    </label>
                    <label class="contact-form-field">
                        <span class="contact-form-label">البريد الإلكتروني</span>
                        <input class="admin-input" type="email" name="email" value="{{ old('email') }}" required placeholder="name@example.com">
                    </label>
                    <label class="contact-form-field">
                        <span class="contact-form-label">الجوال <span class="contact-form-optional">(اختياري)</span></span>
                        <input class="admin-input" name="phone" value="{{ old('phone') }}" maxlength="40" placeholder="+966 ...">
                    </label>

                    <div class="contact-mode-panel" data-mode="consultation" @if($activeMessageType !== 'consultation') hidden @endif>
                        <div class="contact-intake-intro">
                            <h4>ماذا سنراجع من هذا الملف؟</h4>
                            <ul>
                                <li>وضع مشروعك الحالي وما الذي يعيق النمو فعليًا.</li>
                                <li>الجمهور والعرض والقنوات والأولوية التالية.</li>
                                <li>ما الذي يمكن تحويله مباشرة إلى خطوة عمل داخل المنصة.</li>
                            </ul>
                        </div>

                        <div class="contact-grid-2">
                            <label class="contact-form-field">
                                <span class="contact-form-label">اسم الشركة أو المشروع</span>
                                <input class="admin-input" name="company_name" value="{{ old('company_name') }}" maxlength="160" placeholder="اسم النشاط أو العلامة">
                            </label>
                            <label class="contact-form-field">
                                <span class="contact-form-label">السوق / الدولة</span>
                                <input class="admin-input" name="market" value="{{ old('market') }}" maxlength="120" placeholder="السعودية، الخليج، سوق عربي...">
                            </label>
                        </div>

                        <label class="contact-form-field">
                            <span class="contact-form-label">ما الذي يقدمه مشروعك اليوم؟</span>
                            <textarea class="admin-input" name="business_summary" rows="4" maxlength="1600" placeholder="صف النشاط الحالي، نوع العملاء، وطبيعة البيع أو الخدمة.">{{ old('business_summary') }}</textarea>
                        </label>

                        <label class="contact-form-field">
                            <span class="contact-form-label">ما العرض أو الخدمة الأساسية التي تريد بيعها؟</span>
                            <textarea class="admin-input" name="offer" rows="3" maxlength="1600" placeholder="العرض الرئيسي، الفئة، أو الخدمة التي تريد دفعها للأمام.">{{ old('offer') }}</textarea>
                        </label>

                        <label class="contact-form-field">
                            <span class="contact-form-label">من هو العميل المثالي؟</span>
                            <textarea class="admin-input" name="ideal_customer" rows="3" maxlength="1600" placeholder="من تريد جذبه تحديدًا؟ وما صفاته أو مرحلته؟">{{ old('ideal_customer') }}</textarea>
                        </label>

                        <label class="contact-form-field">
                            <span class="contact-form-label">ما ألم هذا الجمهور أو مشكلته الأساسية؟</span>
                            <textarea class="admin-input" name="pain_points" rows="3" maxlength="1600" placeholder="ما الذي يجعلهم يبحثون عن حل الآن؟">{{ old('pain_points') }}</textarea>
                        </label>

                        <div class="contact-grid-2">
                            <label class="contact-form-field">
                                <span class="contact-form-label">ما هدفك الأقرب الآن؟</span>
                                <input class="admin-input" name="primary_goal" value="{{ old('primary_goal') }}" maxlength="500" placeholder="مثال: رفع جودة العملاء المحتملين">
                            </label>
                            <label class="contact-form-field">
                                <span class="contact-form-label">كيف ستقيس النجاح؟</span>
                                <input class="admin-input" name="success_metric" value="{{ old('success_metric') }}" maxlength="500" placeholder="مثال: عدد الاستفسارات المؤهلة">
                            </label>
                        </div>

                        <div class="contact-grid-2">
                            <label class="contact-form-field">
                                <span class="contact-form-label">الإطار الزمني</span>
                                <input class="admin-input" name="timeframe" value="{{ old('timeframe') }}" maxlength="120" placeholder="30 يوم، ربع سنوي، خلال الإطلاق...">
                            </label>
                            <label class="contact-form-field">
                                <span class="contact-form-label">نطاق الميزانية</span>
                                <select class="admin-input" name="budget_range">
                                    <option value="">اختر نطاقًا تقريبيًا</option>
                                    @foreach($budgetRanges as $range)
                                        <option value="{{ $range }}" @selected(old('budget_range') === $range)>{{ $range }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <fieldset class="contact-form-field">
                            <legend class="contact-form-label">ما القنوات التي تعمل عليها الآن؟</legend>
                            <div class="contact-chip-grid">
                                @foreach($consultationChannels as $channel)
                                    <label class="contact-chip">
                                        <input type="checkbox" name="current_channels[]" value="{{ $channel }}" @checked(in_array($channel, old('current_channels', []), true))>
                                        <span>{{ $channel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <label class="contact-form-field">
                            <span class="contact-form-label">كيف تصف وضع التسويق الحالي باختصار؟</span>
                            <textarea class="admin-input" name="current_state" rows="3" maxlength="1600" placeholder="ما الذي تم بناؤه؟ وما الذي لا يعمل جيدًا؟">{{ old('current_state') }}</textarea>
                        </label>

                        <label class="contact-form-field">
                            <span class="contact-form-label">ما الأولوية أو عنق الزجاجة الآن؟</span>
                            <textarea class="admin-input" name="priority" rows="3" maxlength="500" placeholder="مثال: العرض غير واضح، الرسائل ضعيفة، التحويل منخفض، المحتوى مشتت...">{{ old('priority') }}</textarea>
                        </label>

                        <fieldset class="contact-form-field">
                            <legend class="contact-form-label">ما نوع المخرجات التي تحتاجها غالبًا؟</legend>
                            <div class="contact-chip-grid">
                                @foreach($consultationServices as $service)
                                    <label class="contact-chip">
                                        <input type="checkbox" name="services[]" value="{{ $service }}" @checked(in_array($service, old('services', []), true))>
                                        <span>{{ $service }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <label class="contact-form-field">
                            <span class="contact-form-label">ملاحظات إضافية</span>
                            <textarea class="admin-input" name="additional_context" rows="4" maxlength="4000" placeholder="أي سياق إضافي: منافسون، فريق داخلي، ملفات حالية، أو ما جرّبته سابقًا.">{{ old('additional_context') }}</textarea>
                        </label>
                    </div>

                    <div class="contact-mode-panel" data-mode="general" @if($activeMessageType !== 'general') hidden @endif>
                        <label class="contact-form-field">
                            <span class="contact-form-label">الموضوع</span>
                            <input class="admin-input" name="subject" value="{{ old('subject') }}" maxlength="200" placeholder="عنوان مختصر لطلبك">
                        </label>
                        <label class="contact-form-field">
                            <span class="contact-form-label">الرسالة</span>
                            <textarea class="admin-input" name="body" rows="6" maxlength="10000" placeholder="اشرح طلبك بوضوح — كلما كان التفصيل أدق، كان الرد أفضل.">{{ old('body') }}</textarea>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-full">{{ $activeMessageType === 'consultation' ? 'إرسال ملف الاستشارة' : 'إرسال الرسالة' }}</button>
                    <p class="contact-form-note">
                        بإرسال هذا النموذج أنت توافق على
                        <a href="{{ route('privacy') }}" class="contact-form-note-link">سياسة الخصوصية</a>.
                    </p>
                </form>
            </div>
        </div>
    </div>
</section>

{{-- ═══ FAQ ═══ --}}
<section class="section-lg section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal mb-8">
            <p class="text-eyebrow mb-3 text-p">أسئلة شائعة</p>
            <h2 class="heading-lg">قبل أن تُرسل <span class="text-gradient">رسالتك</span></h2>
        </div>

        <div class="contact-faq-list max-w-3xl mx-auto">
            @foreach($contactFaqs as $i => $faq)
            <details class="contact-faq-item reveal d-{{ ($i % 3) + 1 }}">
                <summary class="contact-faq-question">
                    <span>{{ $faq['q'] }}</span>
                    <svg class="contact-faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </summary>
                <div class="contact-faq-answer">{{ $faq['a'] }}</div>
            </details>
            @endforeach
        </div>
    </div>
</section>
@endsection

@push('head')
<style>
    .contact-mode-switch {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--sp-3);
    }
    .contact-mode-option {
        position: relative;
        display: flex;
        align-items: center;
    }
    .contact-mode-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .contact-mode-option span {
        width: 100%;
        padding: 14px 16px;
        border-radius: var(--r-md);
        border: 1px solid var(--border);
        background: var(--surface-2);
        color: var(--text);
        font-size: var(--fs-sm);
        font-weight: 800;
        text-align: center;
        transition: border-color var(--dur-base) var(--ease), background var(--dur-base) var(--ease), color var(--dur-base) var(--ease);
    }
    .contact-mode-option input:checked + span {
        border-color: var(--p);
        background: color-mix(in srgb, var(--p) 12%, var(--surface-2));
        color: var(--p);
    }

    /* — قنوات التواصل — */
    .contact-channel-meta {
        margin-top: var(--sp-3);
        padding-top: var(--sp-3);
        border-top: 1px dashed var(--border);
        font-size: var(--fs-xs);
        font-weight: 700;
        color: var(--p);
        letter-spacing: 0.02em;
    }

    /* — بطاقة النموذج — */
    .contact-form-title {
        font-size: var(--fs-lg);
        font-weight: 900;
        color: var(--text);
        margin-bottom: var(--sp-2);
    }
    .contact-form-subtitle {
        font-size: var(--fs-sm);
        color: var(--text-muted);
        margin-bottom: var(--sp-5);
    }
    .contact-form {
        display: flex;
        flex-direction: column;
        gap: var(--sp-4);
    }
    .contact-mode-panel {
        display: flex;
        flex-direction: column;
        gap: var(--sp-4);
    }
    .contact-form-field {
        display: flex;
        flex-direction: column;
        gap: var(--sp-2);
    }
    .contact-form-label {
        font-size: var(--fs-sm);
        font-weight: 700;
        color: var(--text);
    }
    .contact-form-optional {
        font-size: var(--fs-xs);
        color: var(--text-muted);
        font-weight: 500;
    }
    .contact-form .admin-input {
        width: 100%;
    }
    .contact-form-note {
        font-size: var(--fs-xs);
        color: var(--text-muted);
        text-align: center;
        margin-top: var(--sp-2);
        line-height: 1.6;
    }
    .contact-form-note-link {
        color: var(--p);
        font-weight: 700;
        text-decoration: none;
    }
    .contact-form-note-link:hover { text-decoration: underline; }
    .contact-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--sp-4);
    }
    .contact-intake-intro {
        padding: var(--sp-4);
        border-radius: var(--r-md);
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--p) 8%, var(--surface-2));
    }
    .contact-intake-intro h4 {
        font-size: var(--fs-sm);
        font-weight: 900;
        color: var(--text);
        margin-bottom: var(--sp-2);
    }
    .contact-intake-intro ul {
        margin: 0;
        padding-inline-start: 1.15rem;
        color: var(--text-muted);
        font-size: var(--fs-xs);
        line-height: 1.9;
    }
    .contact-chip-grid {
        display: flex;
        flex-wrap: wrap;
        gap: var(--sp-2);
    }
    .contact-chip {
        position: relative;
        display: inline-flex;
        align-items: center;
    }
    .contact-chip input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .contact-chip span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 9px 14px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--surface-2);
        color: var(--text-muted);
        font-size: var(--fs-xs);
        font-weight: 700;
        transition: border-color var(--dur-base) var(--ease), color var(--dur-base) var(--ease), background var(--dur-base) var(--ease);
    }
    .contact-chip input:checked + span {
        border-color: var(--p);
        color: var(--p);
        background: color-mix(in srgb, var(--p) 12%, var(--surface-2));
    }

    /* — معلومات التواصل الإضافية — */
    .contact-info-blocks {
        display: flex;
        flex-direction: column;
        gap: var(--sp-4);
    }
    .contact-info-block {
        display: flex;
        align-items: center;
        gap: var(--sp-4);
        padding: var(--sp-4);
        border-radius: var(--r-md);
        border: 1px solid var(--border);
        background: var(--surface-2);
    }
    .contact-info-icon {
        width: 40px; height: 40px;
        flex-shrink: 0;
        border-radius: var(--r-md);
        display: flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--p) 12%, transparent);
        color: var(--p);
    }
    .contact-info-label {
        font-size: var(--fs-xs);
        font-weight: 700;
        color: var(--text-muted);
        margin: 0 0 4px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .contact-info-value {
        font-size: var(--fs-sm);
        font-weight: 700;
        color: var(--text);
        margin: 0;
    }

    /* — FAQ — */
    .contact-faq-list {
        display: flex;
        flex-direction: column;
        gap: var(--sp-3);
    }
    .contact-faq-item {
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        background: var(--surface-2);
        overflow: hidden;
        transition: border-color var(--dur-base) var(--ease);
    }
    .contact-faq-item:hover { border-color: var(--p); }
    .contact-faq-item[open] { border-color: var(--p); }
    .contact-faq-question {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--sp-4);
        padding: var(--sp-5) var(--sp-6);
        cursor: pointer;
        list-style: none;
        font-size: var(--fs-md);
        font-weight: 800;
        color: var(--text);
    }
    .contact-faq-question::-webkit-details-marker { display: none; }
    .contact-faq-chevron {
        flex-shrink: 0;
        color: var(--p);
        transition: transform var(--dur-base) var(--ease);
    }
    .contact-faq-item[open] .contact-faq-chevron {
        transform: rotate(180deg);
    }
    .contact-faq-answer {
        padding: 0 var(--sp-6) var(--sp-5);
        font-size: var(--fs-sm);
        color: var(--text-muted);
        line-height: 1.9;
    }

    @media (max-width: 860px) {
        .contact-grid-2,
        .contact-mode-switch {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('contact-intake-form');
        if (!form) {
            return;
        }

        const radios = form.querySelectorAll('input[name="message_type"]');
        const panels = form.querySelectorAll('.contact-mode-panel');
        const submitButton = form.querySelector('button[type="submit"]');

        const syncMode = () => {
            const active = form.querySelector('input[name="message_type"]:checked')?.value || 'consultation';

            panels.forEach((panel) => {
                const isActive = panel.dataset.mode === active;
                panel.hidden = !isActive;
            });

            if (submitButton) {
                submitButton.textContent = active === 'consultation'
                    ? 'إرسال ملف الاستشارة'
                    : 'إرسال الرسالة';
            }
        };

        radios.forEach((radio) => radio.addEventListener('change', syncMode));
        syncMode();
    });
</script>
@endpush
