@extends('layouts.public')
@section('layout', 'marketing')

{{--
    العنوان يبدأ بالفعل الذي يطلبه الزائر من نفسه («شخّص… وحدد») ثم يسمّي
    القطاعات الثلاثة. كلمة «متخصص» وحدها ادّعاء تفوّق بلا سند، والقطاعات
    المسمّاة تقول الشيء نفسه بدليل يمكن التحقق منه في صفحاتها.
--}}
@section('title', 'خالد سعد | شخّص تسويق مشروعك وحدد أولوياتك — التعليم والتجارة الإلكترونية والعقارات')
@section('description', 'تجيب عن أسئلة عن مشروعك، ونفحص ما يمكن فحصه من موقعك، فتخرج بدرجة وقائمة فجوات مرتّبة. الأسئلة والفحوصات والمعايير مبنية لقطاعات التعليم والتجارة الإلكترونية والعقارات، وبقية القطاعات تمرّ بالمسار الكامل نفسه.')

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
                    {{--
                        العنوان كان حكمًا عامًّا على التسويق («كثرة التسويق لا تعني…»)،
                        وهو وعظ لا يخصّ قارئه. صار سؤالًا يسأله صاحب النشاط لنفسه، كما
                        في اللوحة بعد الدخول: «هل تظهر في إجابات النماذج؟».
                    --}}
                    <p class="eyebrow reveal">قبل أن تزيد الإنفاق · اعرف أين تقف الآن</p>
                    <h1 class="reveal">
                        أين يتعطّل تسويق مشروعك؟
                        <span>ابدأ بتشخيص يعطيك درجة وفجوات بالاسم.</span>
                    </h1>
                    <p class="hero-lead reveal">
                        تجيب عن أسئلة عن مشروعك، ونفحص ما يمكن فحصه من موقعك.
                        تخرج بدرجة من 100، وبقائمة فجوات مرتّبة على الأثر والجهد.
                    </p>
                    <p class="hero-lead reveal" aria-label="قطاعات التخصص">
                        إن كنت في <strong>التعليم</strong> أو <strong>التجارة الإلكترونية</strong>
                        أو <strong>العقارات</strong>، نسألك أسئلة ونفحص بنودًا تخص قطاعك وحده،
                        ونقارن درجتك بأنشطة مثلك. وبقية القطاعات تمرّ بالمسار الكامل نفسه.
                    </p>
                    <div class="hero-actions reveal">
                        <a class="button button--primary button--large" href="{{ $startUrl }}">
                            {{ $startLabel }}
                            <span aria-hidden="true">←</span>
                        </a>
                        <a class="button button--ghost button--large" href="{{ route('tools.index') }}">اختر ما تريد تشخيصه</a>
                        <a class="button button--ghost button--large" href="{{ route('mobile.download') }}">تنزيل تطبيق أندرويد</a>
                    </div>
                    {{--
                        الشريط كان معنونًا «ما الذي يميّز الطريقة» فيقرأ ادّعاء تفوّق
                        على أحد. الأرقام نفسها ليست تمييزًا، بل ما يحتاج الزائر معرفته
                        قبل أن ينقر: كم يستغرق، وهل يُطلب منه دفع.
                    --}}
                    <ul class="hero-trust reveal" aria-label="ما تحتاج معرفته قبل أن تبدأ">
                        <li><strong>+10</strong><span>سنوات خبرة عملية</span></li>
                        <li><strong>نحو 10 دقائق</strong><span>للتشخيص الأول</span></li>
                        <li><strong>ابدأ مباشرة</strong><span>من دون بطاقة دفع</span></li>
                    </ul>
                </div>

                <div class="hero-visual reveal" aria-label="مثال بصري لنتيجة التشخيص">
                    <div class="result-window">
                        <div class="result-window__top">
                            <span>مثال لما ستستلمه</span>
                            <span class="live-dot">مثال توضيحي — ليس نتيجة عميل</span>
                        </div>
                        <div class="score-panel">
                            <div class="score-ring" style="--score: 64">
                                <strong>64</strong>
                                <span>من 100</span>
                            </div>
                            <div>
                                {{--
                                    كان هنا حكم عام على «المشكلة» في كل مشروع، وهو جملة
                                    سببية بصيغة الجزم عن نشاط لم يُقَس بعد. صار وصفًا
                                    لما تحويه البطاقة فعلًا، والرقم يتكفّل بالباقي.
                                --}}
                                <p class="status-label">مشروع تجارة إلكترونية · مثال</p>
                                <h2>أعلى ثلاث فجوات، مرتّبة على الأثر والجهد.</h2>
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
                            <span>3 فجوات بالاسم</span>
                            <span>قائمة إصلاح مرتّبة</span>
                            <span>خطوة تبدأ بها اليوم</span>
                        </div>
                    </div>
                    <div class="floating-note floating-note--top">
                        <span>ابدأ من هنا</span>
                        <strong>اكتب سبب الشراء منك قبل زيادة الإعلان</strong>
                    </div>
                    <div class="floating-note floating-note--bottom">
                        <span class="pulse-icon"></span>
                        <div><strong>ابدأ من دون دفع</strong><small>لا نطلب بطاقة عند بدء التشخيص</small></div>
                    </div>
                </div>
            </div>

            {{--
                الشريط كان يعرض تخصصات مهنية («استراتيجية التسويق · تحليل
                البيانات») ويسمّي قطاعًا واحدًا من الثلاثة، فينقض جملة التخصص
                التي تسبقه بثمانين سطرًا. صار يحمل القطاعات نفسها موصولةً
                بصفحاتها — ووعدٌ يتكرر مرتين متسقتين أقوى من وعد يناقض نفسه.
            --}}
            <div class="container hero-strip" aria-label="قطاعات التخصص">
                @foreach (\App\Modules\Shared\Sectors\Sector::SPECIALIZED as $index => $sectorKey)
                    @if ($index > 0)<i></i>@endif
                    <a href="{{ route('sectors.show', $sectorKey) }}">{{ \App\Modules\Shared\Sectors\Sector::label($sectorKey) }}</a>
                @endforeach
                <i></i>
                <span class="muted">وبقية القطاعات بالمسار الكامل نفسه</span>
            </div>
        </section>

        @if ($proof !== null)
            {{-- برهان حقيقي من بيانات فعلية، لا شعار: يظهر فقط حين تكفي العينة (§٤.٢) --}}
            <section class="proof-strip" aria-label="نشاط المنصة الفعلي">
                <div class="container proof-strip__inner">
                    <p>
                        <strong>{{ \App\Support\Presentation\Num::int($proof['count']) }}</strong>
                        تشخيصًا اكتمل خلال آخر 30 يومًا
                    </p>
                    <p>
                        متوسط الدرجة <strong>{{ \App\Support\Presentation\Num::score($proof['average']) }}</strong>
                        <span class="muted">— من {{ \App\Support\Presentation\Num::int($proof['count']) }} تشخيصًا مكتملًا</span>
                    </p>
                </div>
            </section>
        @endif

        <section class="section problems-section" id="why">
            <div class="container">
                {{--
                    الوصف كان يحكم على سبب المشكلة قبل أي قياس («المشكلة ليست في حجم
                    العمل، بل في…»)، وهذا استنتاج معروض كحقيقة. صار يقول ما يفعله
                    التشخيص لا ما يفترضه عن القارئ.
                --}}
                <x-section-heading
                    eyebrow="ابدأ من هنا"
                    title="هل تصف إحدى هذه الحالات مشروعك؟"
                    description="اختر الأقرب إلى حالتك. من هنا يبدأ التشخيص: نحدد أين يذهب المال والوقت قبل أن نقترح عليك شيئًا."
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
                        <strong>كل فجوة تخرج لك بالاسم، ومعها ترتيبها على الأثر والجهد.</strong>
                        تعرف ما تبدأ به هذا الأسبوع، وما يؤجَّل بلا ندم.
                    </p>
                    <a href="{{ $startUrl }}">ابدأ التشخيص الأول <span aria-hidden="true">←</span></a>
                    <a href="{{ route('services') }}">افهم المشكلات والمخرجات <span aria-hidden="true">←</span></a>
                </div>
            </div>
        </section>

        <section class="section services-section" id="services">
            <div class="container">
                <div class="split-heading">
                    {{--
                        العنوان الفرعي كان يبيع الخبرة («عشر سنوات في خدمتك») في موضع
                        يتكلّم عن مخرجات العميل. الخبرة لها قسمها أدناه بالتواريخ، وهنا
                        يقرأ الزائر ما يخرج به هو.
                    --}}
                    <x-section-heading
                        eyebrow="ما تخرج به"
                        title="ما الذي سيساعدك على اتخاذ قرار أوضح؟"
                        description="لا نبدأ من إعلان ولا من منصة. نبدأ من سؤال: ما أهم شيء تحتاج حلّه الآن؟"
                        align="start"
                    />
                    <p class="split-heading__aside">تبدأ من سؤال «أين أبدأ؟»، وتخرج بأولويات مرتّبة يمكنك تنفيذها أو تسليمها لفريقك.</p>
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
                <a class="text-link" href="{{ route('services') }}">استعرض صفحة المشكلات والمخرجات كاملة <span aria-hidden="true">←</span></a>
            </div>
        </section>

        <section class="section method-section" id="method">
            <div class="container method-layout">
                <div class="method-copy">
                    <x-section-heading
                        eyebrow="كيف تصل إلى النتيجة"
                        title="من إجاباتك إلى قائمة إصلاح مرتّبة"
                        description="تعطينا معلومات مشروعك مرة واحدة، وتخرج بتشخيص يقول ما يحتاج إجراءً الآن وما ينتظر."
                        align="start"
                    />
                    {{--
                        الاقتباس كان حكمة عامة عن «أفضل تشخيص». استُبدل بقاعدة العرض
                        التي تلتزم بها التقارير فعلًا (§٤.١ و§١٣): رقم بلا أساسه لا
                        يُعرض. الوعد الذي يمكن التحقق منه أنفع من عبارة تُعجب.
                    --}}
                    <div class="method-quote">
                        <span aria-hidden="true">“</span>
                        <p>كل رقم في تقريرك يأتي معه أساسه: مِمَّ حُسب، وكم بندًا فُحص، وما وُسم منه فرضية.</p>
                    </div>
                    <a class="text-link" href="{{ route('methodology') }}">اقرأ منهجية العمل كاملة <span aria-hidden="true">←</span></a>
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
                    eyebrow="اختر الأولوية"
                    title="ما الذي تريد فهمه أو تحسينه الآن؟"
                    description="اختر التحدي الأقرب إلى مشروعك. كل بطاقة تقول ما تقيسه وكم تستغرق، ثم تنتقل من الأسئلة إلى خطوات تنفّذها أو تسلّمها لفريقك."
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
                                {{-- نفس نداء اللوحة بعد الدخول، حتى لا يتبدّل اللسان عند التسجيل. --}}
                                <em>اعرف التفاصيل وابدأ ←</em>
                            </span>
                        </a>
                    @endforeach
                </div>

                <div class="tools-more reveal">
                    <a class="button button--ghost button--large" href="{{ route('tools.index') }}">
                        اطّلع على كل التشخيصات
                        <span aria-hidden="true">←</span>
                    </a>
                    <a class="text-link" href="{{ $startUrl }}">{{ $startLabel }} <span aria-hidden="true">←</span></a>
                </div>
            </div>
        </section>

        <section class="section sample-section" id="sample">
            <div class="container sample-layout">
                <div class="sample-copy">
                    {{--
                        «مثال توضيحي» وحدها تُقرأ أحيانًا نتيجة عميل حقيقي، فأُلحق بها
                        نفيها الصريح كما في صفحة الأداة. والبنود صارت تسمّي ما يظهر في
                        التقرير فعلًا (وسم «مقيس» مقابل «فرضية») بدل وصف عام لجودته.
                    --}}
                    <x-section-heading
                        eyebrow="مثال توضيحي — ليس نتيجة عميل"
                        title="هكذا تساعدك النتيجة على اتخاذ القرار"
                        description="ترى درجتك، والفجوات التي وراءها، والخطوة التالية. وكل مصطلح مشروح في موضعه."
                        align="start"
                    />
                    <ul class="check-list">
                        <li><span>✓</span> درجة من 100، ومعها عدد المحاور التي حُسبت منها</li>
                        <li><span>✓</span> ما فُحص من موقعك موسوم «مقيس»، وما بُني على كلامك موسوم «فرضية»</li>
                        <li><span>✓</span> الفجوات مرتّبة على الأثر والجهد: ابدأ بهذه، وأجّل تلك</li>
                        <li><span>✓</span> كل توصية تتحول إلى مهمة لها موعد</li>
                    </ul>
                    <a class="text-link" href="{{ route('sample-report') }}">افتح نموذج النتيجة كاملًا <span aria-hidden="true">←</span></a>
                </div>

                <div class="sample-report reveal">
                    <div class="sample-report__header">
                        <div>
                            <small>تقرير التشخيص</small>
                            <strong>مشروع تجارة إلكترونية</strong>
                        </div>
                        <span>مثال توضيحي</span>
                    </div>
                    <div class="sample-report__score">
                        <strong>64<small>/100</small></strong>
                        <div>
                            <span>درجة الجاهزية</span>
                            <div class="report-bar"><i style="width: 64%"></i></div>
                        </div>
                    </div>
                    {{--
                        أسماء الفجوات كانت مصطلحات مهنية («وعد القيمة»، «رحلة التحويل»)
                        يقرأها صاحب النشاط ولا يعرف ماذا يفعل بها. §١٣ يمنع المصطلح بلا
                        شرح في جملته، وأقصر شرحٍ أن تُكتب الفجوة بلسان صاحبها.
                    --}}
                    <div class="sample-report__grid">
                        <article><span>الفجوة 01</span><strong>سبب الشراء منك غير مكتوب</strong><small>أثر مرتفع · جهد منخفض</small></article>
                        <article><span>الفجوة 02</span><strong>لا توجد صفحة تنقل الزائر إلى الشراء</strong><small>أثر مرتفع · جهد متوسط</small></article>
                        <article><span>الفجوة 03</span><strong>لا تعرف من أين جاءك العميل</strong><small>أثر متوسط · جهد منخفض</small></article>
                    </div>
                    <div class="sample-report__next">
                        <span>الخطوة التالية</span>
                        <strong>اكتب سبب الشراء منك في صفحتك الأولى، وابنِ صفحة الشراء، قبل زيادة ميزانية الإعلان.</strong>
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
                        <p class="eyebrow">من يقف خلف المنهجية</p>
                        <h2>عني</h2>
                        {{-- الجملة تقول مدة الخبرة ومجالها فقط، والتفصيل بالتواريخ تحتها يغني عن الوصف. --}}
                        <p class="about-lead">أكثر من 10 سنوات في بناء الحملات وقيادة الفرق وقراءة البيانات. المسار كاملًا بالتواريخ أدناه.</p>
                        @foreach ($brand['about'] as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                        <div class="profile-links">
                            <a href="{{ route('profile') }}">السيرة المهنية الكاملة <span>←</span></a>
                            <a href="{{ $brand['contact']['linkedin'] }}" target="_blank" rel="noopener noreferrer">LinkedIn <span>↗</span></a>
                            <a href="{{ $brand['contact']['x'] }}" target="_blank" rel="noopener noreferrer">X / Twitter <span>↗</span></a>
                        </div>
                    </div>
                </div>

                <div class="profile-grid">
                    <section class="timeline-panel">
                        <div class="panel-heading">
                            <div><span>المسار المهني</span><h3>المواقع والجهات بالتواريخ</h3></div>
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
                            @foreach (array_slice($brand['credentials'], 0, 4) as $credential)
                                <div class="credential">
                                    <span aria-hidden="true">✓</span>
                                    <strong>{{ $credential['name'] }}</strong>
                                </div>
                            @endforeach
                            <a class="text-link" href="{{ route('profile') }}#profile-credentials">استعرض الشهادات الـ{{ count($brand['credentials']) }} <span aria-hidden="true">←</span></a>
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
                    <p class="eyebrow">ما نلتزم به</p>
                    <h2>ما الذي يمكنك أن تتوقعه؟</h2>
                    {{--
                        القسم كله وعد، فلا يصحّ أن يُكتب بلغة الوعد. صار كل بند جملة
                        واحدة قابلة للتحقق من التقرير نفسه: الوسم، والمهمة، والموعد.
                    --}}
                    <p>لا نعدك برقم. نعدك بأن تعرف كيف حُسب كل رقم تراه، وما وُسم منه «فرضية» لأنه مبنيّ على كلامك لا على قياس.</p>
                    <a class="text-link" href="{{ route('principles') }}">اقرأ مبادئ العمل كاملة <span aria-hidden="true">←</span></a>
                </div>
                <div class="principles-grid">
                    <article class="reveal"><span>01</span><h3>لا توصية من دون سببها</h3><p>كل خطوة مقترحة معها الفجوة التي جاءت منها وأثرها المتوقع.</p></article>
                    <article class="reveal"><span>02</span><h3>القرار يبقى قرارك</h3><p>التشخيص يرتّب لك الصورة، والدرجة مبنيّة على إجاباتك وعلى ما فُحص من موقعك.</p></article>
                    <article class="reveal"><span>03</span><h3>لا تشخيص من دون متابعة</h3><p>ينتهي التشخيص بمهام لها مواعيد ومؤشرات تقيس بها تقدمك.</p></article>
                    <article class="reveal"><span>04</span><h3>معلوماتك تخصك أنت</h3><p>نستخدمها لتحليل مشروعك فقط، ولا نعرضها ولا نستخدمها كمثال أمام الناس.</p></article>
                </div>
            </div>
        </section>

        <section class="section knowledge-section" id="knowledge">
            <div class="container">
                <div class="split-heading">
                    <x-section-heading
                        eyebrow="المكتبة المعرفية"
                        title="محتوى يحول المعرفة إلى خطوات"
                        description="مقالات ودروس ومحاضرات ودورات عملية: افهم المشكلة وطبّق خطوة واضحة يمكنك قياس أثرها."
                        align="start"
                    />
                    <a class="text-link" href="{{ route('knowledge') }}">استعرض صفحة المعرفة <span>←</span></a>
                </div>
                <div class="knowledge-grid">
                    @forelse ($knowledge as $index => $item)
                        <a class="knowledge-card reveal" href="{{ route('content.show', $item) }}">
                            <div class="knowledge-card__visual knowledge-card__visual--{{ $index + 1 }}">
                                <span>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <i></i>
                            </div>
                            <small>{{ ['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'][$item->type] }}</small>
                            <h3>{{ $item->title }}</h3>
                            <p>{{ $item->excerpt }}</p>
                            <span class="knowledge-card__link">عرض المحتوى <b>←</b></span>
                        </a>
                    @empty
                        <div class="knowledge-card reveal">
                            <small>النشرة المهنية</small>
                            <h3>{{ $brand['knowledge'][0]['title'] }}</h3>
                            <p>{{ $brand['knowledge'][0]['description'] }}</p>
                            <a class="text-link" href="{{ route('knowledge') }}">استعرض المعرفة والمحتوى <span>←</span></a>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="section faq-section" id="faq">
            <div class="container faq-layout">
                <div class="faq-copy">
                    <x-section-heading
                        eyebrow="قبل أن تبدأ"
                        title="الأسئلة الشائعة"
                        description="إن لم تجد سؤالك هنا، تواصل مباشرة."
                        align="start"
                    />
                    <div class="faq-contact">
                        <span>سؤالك غير موجود؟</span>
                        <a href="{{ route('faq') }}">افتح صفحة الأسئلة والتواصل <b>←</b></a>
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
                        <h2>ابدأ تشخيص مشروعك الآن.</h2>
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
                                {{-- نفس نصّ الزر في اللوحة، فمن يدخل يجد ما وُعد به بالاسم نفسه. --}}
                                ابدأ التشخيص الذكي الشامل
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
