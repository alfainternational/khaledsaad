@extends('layouts.public')
@section('layout', 'marketing')

@section('title', 'خالد سعد | شخّص تسويق مشروعك وحدد أولوياتك')
@section('description', 'ابدأ بتشخيص واضح لتسويق مشروعك، واكتشف أهم الفجوات والخطوات التي تستحق التنفيذ قبل زيادة الوقت أو الميزانية.')

@section('content')
    @php
        // مسار البداية الحقيقي: تجربة تبدأ فورًا، لا مرساة تعيدك لأعلى الصفحة.
        $startUrl = auth()->check()
            ? route('app.dashboard')
            : route('register');
        $startLabel = auth()->check() ? 'افتح لوحة مشروعك' : 'ابدأ تشخيص مشروعك';
    @endphp

    @include('partials.site-header', ['startTool' => $entryTool !== null ? ['tool' => $entryTool['key']] : []])

    <main id="main-content">
        <section id="top" class="hero">
            <div class="hero-orb hero-orb--blue" aria-hidden="true"></div>
            <div class="hero-orb hero-orb--orange" aria-hidden="true"></div>

            <div class="container hero-grid layout-hero">
                <div class="hero-copy">
                    <p class="eyebrow reveal">قبل أن تزيد الإنفاق · اعرف ما يحتاجه مشروعك أولًا</p>
                    <h1 class="reveal">
                        كثرة التسويق لا تعني نموًا أفضل.
                        <span>ابدأ بتشخيص يوضح أولوياتك.</span>
                    </h1>
                    <p class="hero-lead reveal">
                        أجب عن أسئلة واضحة حول مشروعك، لتحصل على صورة أقرب إلى واقعك:
                        أين تتعطل النتائج، وما الخطوات التي تستحق أن تبدأ بها الآن.
                    </p>
                    <div class="hero-actions reveal">
                        <a class="button button--primary button--large" href="{{ $startUrl }}">
                            {{ $startLabel }}
                            <span aria-hidden="true">←</span>
                        </a>
                        <a class="button button--ghost button--large" href="{{ route('tools.index') }}">اختر ما تريد تحسينه</a>
                        <a class="button button--ghost button--large" href="{{ route('mobile.download') }}">تنزيل تطبيق أندرويد</a>
                    </div>
                    <ul class="hero-trust reveal" aria-label="ما الذي يميّز الطريقة">
                        <li><strong>+10</strong><span>سنوات من الخبرة العملية</span></li>
                        <li><strong>نحو 10 دقائق</strong><span>للتشخيص الأولي</span></li>
                        <li><strong>ابدأ مباشرة</strong><span>من دون بطاقة دفع</span></li>
                    </ul>
                </div>

                <div class="hero-visual reveal" aria-label="مثال بصري لنتيجة التشخيص">
                    <div class="result-window">
                        <div class="result-window__top">
                            <span>مثال لما ستستلمه</span>
                            <span class="live-dot">مثال توضيحي</span>
                        </div>
                        <div class="score-panel">
                            <div class="score-ring" style="--score: 64">
                                <strong>64</strong>
                                <span>من 100</span>
                            </div>
                            <div>
                                <p class="status-label">وضع المشروع: يحتاج إلى ترتيب الأولويات</p>
                                <h2>المشكلة ليست في قلة الجهد، بل في ترتيب ما يستحقه أولًا.</h2>
                            </div>
                        </div>
                        <div class="mini-findings">
                            <article>
                                <span class="finding-rank">01</span>
                                <div>
                                    <strong>سبب الشراء منك غير واضح</strong>
                                    <small>ابدأ بها</small>
                                </div>
                                <span class="finding-meter"><i style="width: 42%"></i></span>
                            </article>
                            <article>
                                <span class="finding-rank">02</span>
                                <div>
                                    <strong>يهتمون ولا يكملون الشراء</strong>
                                    <small>ابدأ بها</small>
                                </div>
                                <span class="finding-meter"><i style="width: 56%"></i></span>
                            </article>
                            <article>
                                <span class="finding-rank">03</span>
                                <div>
                                    <strong>لا تعرف من أين جاءك العميل</strong>
                                    <small>بعد ذلك</small>
                                </div>
                                <span class="finding-meter"><i style="width: 68%"></i></span>
                            </article>
                        </div>
                        <div class="result-window__footer">
                            <span>3 مشاكل واضحة</span>
                            <span>قائمة إصلاح مرتّبة</span>
                            <span>خطوة تبدأ بها اليوم</span>
                        </div>
                    </div>
                    <div class="floating-note floating-note--top">
                        <span>ابدأ من هنا</span>
                        <strong>وضّح سبب الشراء منك قبل زيادة الإعلان</strong>
                    </div>
                    <div class="floating-note floating-note--bottom">
                        <span class="pulse-icon"></span>
                        <div><strong>ابدأ من دون دفع</strong><small>لا نطلب بطاقة عند بدء التشخيص</small></div>
                    </div>
                </div>
            </div>

            <div class="container hero-strip" aria-label="مجالات الخبرة">
                <span>استراتيجية التسويق</span>
                <i></i>
                <span>التسويق التعليمي</span>
                <i></i>
                <span>تحليل البيانات</span>
                <i></i>
                <span>الذكاء الاصطناعي</span>
                <i></i>
                <span>إدارة المشروعات</span>
            </div>
        </section>

        <section class="section problems-section" id="why">
            <div class="container">
                <x-section-heading
                    eyebrow="قبل أن تجرّب شيئًا جديدًا"
                    title="هل تصف إحدى هذه الحالات مشروعك؟"
                    description="إذا كانت إحداها تصف حالتك، فالمشكلة غالبًا ليست في حجم العمل، بل في عدم وضوح موضع هدر المال والوقت."
                />

                @php $problemArt = ['spend', 'content', 'scatter', 'guess']; @endphp

                <div class="problem-grid">
                    @foreach ($brand['problems'] as $index => $problem)
                        <article class="problem-card reveal">
                            <span class="problem-card__number">0{{ $index + 1 }}</span>
                            <div class="problem-card__art">
                                <x-illustration :name="$problemArt[$index] ?? 'clarity'" />
                            </div>
                            <h3>{{ $problem['title'] }}</h3>
                            <p>{{ $problem['description'] }}</p>
                        </article>
                    @endforeach
                </div>

                <div class="insight-banner reveal">
                    <div class="insight-banner__mark" aria-hidden="true">!</div>
                    <p>
                        <strong>لن تحصل على توصيات عامة يصعب تطبيقها.</strong>
                        سترى ما يستحق البدء به، وما يمكن تأجيله، حتى توجّه وقتك وميزانيتك إلى الأولوية الأوضح.
                    </p>
                    <a href="{{ $startUrl }}">ابدأ التشخيص الأولي <span aria-hidden="true">←</span></a>
                </div>
            </div>
        </section>

        <section class="section services-section" id="services">
            <div class="container">
                <div class="split-heading">
                    <x-section-heading
                        eyebrow="خبرة عشر سنوات في خدمتك"
                        title="ما الذي سيساعدك على اتخاذ قرار أوضح؟"
                        description="لا نبدأ من إعلان ولا من منصة. نبدأ من سؤال: ما أهم شيء تحتاج حلّه الآن؟"
                        align="start"
                    />
                    <p class="split-heading__aside">من سؤال «أين أبدأ؟» إلى أولويات يمكنك مناقشتها وتنفيذها بثقة.</p>
                </div>

                <div class="services-grid">
                    @foreach ($brand['services'] as $service)
                        <article class="service-card reveal">
                            <span>{{ $service['number'] }}</span>
                            <h3>{{ $service['title'] }}</h3>
                            <p>{{ $service['description'] }}</p>
                            <i aria-hidden="true">↗</i>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section method-section" id="method">
            <div class="container method-layout">
                <div class="method-copy">
                    <x-section-heading
                        eyebrow="كيف تصل إلى النتيجة"
                        title="من واقع مشروعك إلى أولويات عملية"
                        description="تقدّم معلومات مشروعك، ثم تحصل على تشخيص مرتب يوضح ما يحتاج إلى إجراء الآن."
                        align="start"
                    />
                    <div class="method-quote">
                        <span aria-hidden="true">“</span>
                        <p>أفضل تشخيص ليس الأطول، بل الذي تعرف كيف قِيس ويمكنك التحرّك عليه غدًا.</p>
                    </div>
                </div>

                <ol class="method-steps">
                    @foreach ($brand['method'] as $step)
                        <li class="reveal">
                            <span>{{ $step['step'] }}</span>
                            <div>
                                <h3>{{ $step['title'] }}</h3>
                                <p>{{ $step['description'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section class="section tools-section" id="tools">
            <div class="container">
                <x-section-heading
                    eyebrow="ابدأ من التحدي الأهم"
                    title="ما الذي تريد فهمه أو تحسينه الآن؟"
                    description="اختر الحالة الأقرب إلى مشروعك، وانتقل من الأسئلة إلى خطوات واضحة يمكنك تنفيذها أو مشاركتها مع فريقك."
                />

                <div class="tools-grid">
                    @foreach ($tools as $index => $tool)
                        <a
                            href="{{ route('tools.show', $tool['key']) }}"
                            @class(['tool-card reveal', 'tool-card--featured' => $index === 0, 'tool-card--soon' => ! $tool['is_runnable']])
                            aria-label="{{ $tool['title'] }}"
                        >
                            <div class="tool-card__head">
                                <span>{{ $tool['category'] }}</span>
                                @unless ($tool['is_runnable'])
                                    <i>قريبًا</i>
                                @endunless
                            </div>
                            <div class="tool-card__symbol" aria-hidden="true">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                            @if ($tool['pain'])
                                <p class="tool-card__pain">«{{ $tool['pain'] }}»</p>
                            @endif
                            <h3>{{ $tool['title'] }}</h3>
                            <p class="tool-card__desc">{{ $tool['promise'] ?: $tool['description'] }}</p>
                            <span class="tool-card__state">
                                @if ($tool['duration_minutes'])
                                    <b>{{ $tool['duration_minutes'] }} دقائق</b>
                                @endif
                                <em>اطّلع على التفاصيل ←</em>
                            </span>
                        </a>
                    @endforeach
                </div>

                <div class="tools-more reveal">
                    <a class="button button--ghost button--large" href="{{ route('tools.index') }}">
                        اطّلع على جميع الحالات
                        <span aria-hidden="true">←</span>
                    </a>
                    <a class="text-link" href="{{ $startUrl }}">{{ $startLabel }} <span aria-hidden="true">←</span></a>
                </div>
            </div>
        </section>

        <section class="section sample-section" id="sample">
            <div class="container sample-layout">
                <div class="sample-copy">
                    <x-section-heading
                        eyebrow="مثال توضيحي"
                        title="هكذا تساعدك النتيجة على اتخاذ القرار"
                        description="ترى وضعك الحالي، وأسباب الفجوات، والخطوة التالية بلغة واضحة بعيدًا عن المصطلحات المعقدة."
                        align="start"
                    />
                    <ul class="check-list">
                        <li><span>✓</span> رقم واضح تقارن به تقدمك بعد شهر</li>
                        <li><span>✓</span> نفرّق بين ما هو أكيد وما يحتاج تأكيدًا منك</li>
                        <li><span>✓</span> ترتيب واضح: ابدأ بهذه، وأجّل تلك</li>
                        <li><span>✓</span> كل توصية تتحول إلى مهمة لها موعد</li>
                    </ul>
                    <a class="text-link" href="{{ $startUrl }}">أنشئ تشخيص مشروعك <span aria-hidden="true">←</span></a>
                </div>

                <div class="sample-report reveal">
                    <div class="sample-report__header">
                        <div>
                            <small>التقرير التنفيذي</small>
                            <strong>مشروع تجارة إلكترونية</strong>
                        </div>
                        <span>نسخة تجريبية</span>
                    </div>
                    <div class="sample-report__score">
                        <strong>64<small>/100</small></strong>
                        <div>
                            <span>درجة الجاهزية</span>
                            <div class="report-bar"><i style="width: 64%"></i></div>
                        </div>
                    </div>
                    <div class="sample-report__grid">
                        <article><span>الفجوة 01</span><strong>وعد القيمة غير محدد</strong><small>أثر مرتفع · جهد منخفض</small></article>
                        <article><span>الفجوة 02</span><strong>لا توجد رحلة تحويل</strong><small>أثر مرتفع · جهد متوسط</small></article>
                        <article><span>الفجوة 03</span><strong>القياس منفصل عن الهدف</strong><small>أثر متوسط · جهد منخفض</small></article>
                    </div>
                    <div class="sample-report__next">
                        <span>الخطوة التالية</span>
                        <strong>إعادة صياغة العرض وبناء صفحة التحويل قبل زيادة ميزانية الحملة.</strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="section about-section" id="about">
            <div class="container">
                <div class="about-intro">
                    <div class="about-monogram reveal" aria-hidden="true">
                        <x-illustration name="experience" />
                    </div>
                    <div class="about-copy">
                        <p class="eyebrow">الخبرة وراء المنهج</p>
                        <h2>عن خالد سعد</h2>
                        <p class="about-lead">خبرة تتجاوز 10 سنوات في بناء الحملات، قيادة الفرق، وتحويل البيانات إلى قرارات تسويقية قابلة للتنفيذ.</p>
                        @foreach ($brand['about'] as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                        <div class="profile-links">
                            <a href="{{ $brand['contact']['linkedin'] }}" target="_blank" rel="noopener noreferrer">LinkedIn <span>↗</span></a>
                            <a href="{{ $brand['contact']['x'] }}" target="_blank" rel="noopener noreferrer">X / Twitter <span>↗</span></a>
                        </div>
                    </div>
                </div>

                <div class="profile-grid">
                    <section class="timeline-panel">
                        <div class="panel-heading">
                            <div><span>المسار المهني</span><h3>خبرات تراكمت عبر قطاعات وأسواق مختلفة</h3></div>
                            <small>2011 — الآن</small>
                        </div>
                        <ol class="experience-timeline">
                            @foreach ($brand['experience'] as $experience)
                                <li class="reveal">
                                    <span class="timeline-dot"></span>
                                    <div>
                                        <small>{{ $experience['period'] }}</small>
                                        <h4>{{ $experience['role'] }}</h4>
                                        <p>{{ $experience['company'] }}</p>
                                        <span>{{ $experience['location'] }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>

                    <div class="profile-side">
                        <section class="profile-card profile-card--education reveal">
                            <span class="profile-card__label">التعليم</span>
                            <div class="profile-card__icon" aria-hidden="true">◇</div>
                            <h3>{{ $brand['education'][0]['degree'] }}</h3>
                            <p>{{ $brand['education'][0]['institution'] }}</p>
                            <small>{{ $brand['education'][0]['period'] }}</small>
                        </section>

                        <section class="profile-card reveal">
                            <span class="profile-card__label">اعتمادات وتعلّم</span>
                            @foreach ($brand['credentials'] as $credential)
                                <div class="credential">
                                    <span aria-hidden="true">✓</span>
                                    <strong>{{ $credential }}</strong>
                                </div>
                            @endforeach
                        </section>

                        <section class="profile-card reveal">
                            <span class="profile-card__label">مجالات العمل</span>
                            <div class="skill-cloud">
                                @foreach ($brand['skills'] as $skill)
                                    <span>{{ $skill }}</span>
                                @endforeach
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </section>

        <section class="section principles-section" id="principles">
            <div class="container principles-layout">
                <div>
                    <p class="eyebrow">وضوح قبل الوعود</p>
                    <h2>ما الذي يمكنك أن تتوقعه؟</h2>
                    <p>لا توجد وصفة واحدة تناسب كل المشاريع، ولا أرقام مضمونة قبل فهم وضعك. ما نقدمه هو فصل واضح بين ما تؤكده بياناتك وما يحتاج إلى تحقق إضافي.</p>
                </div>
                <div class="principles-grid">
                    <article class="reveal"><span>01</span><h3>لا نصيحة من دون سبب</h3><p>كل خطوة مقترحة توضح سببها والأثر المتوقع منها.</p></article>
                    <article class="reveal"><span>02</span><h3>التقنية تساعد ولا تستبدل قرارك</h3><p>يساعدك مسار التشخيص على تنظيم الصورة، وتبقى النتيجة مرتبطة بإجاباتك وما تؤكده بيانات المشروع.</p></article>
                    <article class="reveal"><span>03</span><h3>لا تشخيص من دون متابعة</h3><p>ينتهي التشخيص بمهام ومواعيد ومؤشرات تساعدك على قياس التقدم.</p></article>
                    <article class="reveal"><span>04</span><h3>معلوماتك تخصك أنت</h3><p>نستخدمها لتحليل مشروعك فقط، ولا نعرضها ولا نستخدمها كمثال أمام الناس.</p></article>
                </div>
            </div>
        </section>

        <section class="section knowledge-section" id="knowledge">
            <div class="container">
                <div class="split-heading">
                    <x-section-heading
                        eyebrow="محتوى يفيدك"
                        title="من «يلا نفهم تسويق»"
                        description="مقالات قصيرة أشرح فيها أشياء تحصل معك يوميًا في تسويق مشروعك."
                        align="start"
                    />
                    <a class="text-link" href="{{ $brand['contact']['linkedin'] }}" target="_blank" rel="noopener noreferrer">تابع المزيد <span>↗</span></a>
                </div>
                <div class="knowledge-grid">
                    @foreach ($brand['knowledge'] as $index => $item)
                        <a class="knowledge-card reveal" href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer">
                            <div class="knowledge-card__visual knowledge-card__visual--{{ $index + 1 }}">
                                <span>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <i></i>
                            </div>
                            <small>{{ $item['type'] }}</small>
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['description'] }}</p>
                            <span class="knowledge-card__link">اقرأ على LinkedIn <b>↗</b></span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section faq-section" id="faq">
            <div class="container faq-layout">
                <div class="faq-copy">
                    <x-section-heading
                        eyebrow="أسئلة يسألها أصحاب مشاريع مثلك"
                        title="الأسئلة الشائعة"
                        description="إذا لم تجد سؤالك هنا، يمكنك التواصل مباشرة."
                        align="start"
                    />
                    <div class="faq-contact">
                        <span>سؤالك غير موجود؟</span>
                        <a href="{{ $brand['contact']['whatsapp'] }}" target="_blank" rel="noopener noreferrer">تواصل مباشرة عبر واتساب <b>↗</b></a>
                    </div>
                </div>
                <div class="faq-list">
                    @foreach ($brand['faqs'] as $index => $faq)
                        <details class="faq-item reveal" @if ($index === 0) open @endif>
                            <summary><span>{{ $faq['question'] }}</span><i aria-hidden="true"></i></summary>
                            <p>{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="diagnosis-section" id="diagnosis">
            <div class="container">
                <div class="diagnosis-card">
                    <div class="diagnosis-card__glow" aria-hidden="true"></div>
                    <div class="diagnosis-card__content">
                        <p class="eyebrow">أربع خطوات من السؤال إلى القرار</p>
                        <h2>ابدأ بصورة أوضح عن مشروعك.</h2>
                        <p>ابدأ من دون حساب، أجب عن الأسئلة، ثم أنشئ حسابك لحفظ النتيجة ومتابعة الخطوات المقترحة.</p>

                        <ol class="journey-strip">
                            <li><b>1</b> ابدأ من دون حساب</li>
                            <li><b>2</b> أجب عن أسئلة واضحة</li>
                            <li><b>3</b> أنشئ حسابك لحفظ النتيجة</li>
                            <li><b>4</b> راجع أولوياتك ومهامك</li>
                        </ol>

                        <div class="diagnosis-actions">
                            @if (! auth()->check() && $entryTool !== null)
                                <form method="POST" action="{{ route('try.start', $entryTool['key']) }}">
                                    @csrf
                                    <button type="submit" class="button button--light button--large">
                                        {{ $startLabel }}
                                        <span aria-hidden="true">←</span>
                                    </button>
                                </form>
                            @else
                                <a class="button button--light button--large" href="{{ $startUrl }}">
                                    {{ $startLabel }}
                                    <span aria-hidden="true">←</span>
                                </a>
                            @endif
                            <a class="button button--outline button--large" href="{{ auth()->check() ? route('app.consultations.index') : route('register', ['intent' => 'consultation']) }}">
                                الاستشارة التسويقية الذكية الشاملة
                                <span aria-hidden="true">←</span>
                            </a>
                            <span>من 7 إلى 10 دقائق · كل خطوة تُحفظ · يمكنك العودة في أي وقت</span>
                        </div>
                    </div>
                    <div class="diagnosis-card__mark" aria-hidden="true">
                        <x-illustration name="start" />
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer')
@endsection
