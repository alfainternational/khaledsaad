@extends('layouts.public')
@section('layout', 'marketing')

{{--
    العنوان يبدأ بالفعل الذي يطلبه الزائر من نفسه («شخّص… وحدد») ثم يسمّي
    القطاعات الثلاثة. كلمة «متخصص» وحدها ادّعاء تفوّق بلا سند، والقطاعات
    المسمّاة تقول الشيء نفسه بدليل يمكن التحقق منه في صفحاتها.
--}}
@section('title', 'خالد سعد | شخّص تسويق مشروعك وحدد أولوياتك — التعليم والتجارة الإلكترونية والعقارات')
@section('description', 'تجيب عن أسئلة عن مشروعك، وأفحص ما يمكن فحصه من موقعك، فتخرج بدرجة وقائمة فجوات مرتّبة. الأسئلة والفحوصات والمعايير مبنية لقطاعات التعليم والتجارة الإلكترونية والعقارات، وبقية القطاعات تمرّ بالمسار الكامل نفسه.')

@section('content')
    @php
        // مسار البداية الحقيقي: تجربة تبدأ فورًا، لا مرساة تعيدك لأعلى الصفحة.
        $startUrl = auth()->check()
            ? route('app.dashboard')
            : route('register', ['intent' => 'business']);
        $startLabel = auth()->check() ? __('افتح لوحة مشروعك') : __('شخّص مشروعي');
        $learningUrl = auth()->check()
            ? route('app.learning.marketing.home')
            : route('register', ['intent' => 'learning', 'return_url' => '/app/learn/marketing']);
        $problemIcons = ['spend', 'publish', 'analytics', 'compass'];
        $serviceIcons = ['score', 'ai', 'tasks', 'calendar'];
        $methodIcons = ['listen', 'search', 'sort', 'act'];
        $toolIcons = ['target', 'analytics', 'search', 'priority', 'timeline', 'evidence'];
        $experienceIcons = ['briefcase', 'analytics', 'publish', 'target', 'timeline', 'briefcase'];
    @endphp

    @include('partials.site-header', ['startTool' => $entryTool !== null ? ['tool' => $entryTool['key']] : []])

    <main id="main-content" data-reference-ui="olex">
        <section id="top" class="hero-reference">
            <div class="hero-reference__colour" aria-hidden="true"></div>

            <div class="hero-reference__art" aria-hidden="true">
                <img class="hero-reference__owner" src="{{ asset('assets/design/hero-owner-wrist.png') }}?v=2"
                     alt="" width="1536" height="1024" decoding="sync">
                <img class="hero-reference__device" data-section-art="hero-device-angle.png"
                     src="{{ asset('assets/design/hero-device-angle.png') }}"
                     alt="" width="862" height="2048" fetchpriority="high" decoding="sync">
                <img class="hero-reference__report" src="{{ asset('assets/design/hero-report-float.png') }}"
                     alt="" width="1254" height="1254" decoding="sync">
                <span class="hero-reference__mark">8</span>
            </div>

            <div class="container hero-reference__inner layout-hero">
                <div class="hero-reference__copy">
                    <p class="hero-reference__eyebrow">تشخيص التسويق · خالد سعد</p>
                    <h1>اعرف أين يتعطّل تسويقك</h1>
                    <p class="hero-reference__lead">
                        أجب عن أسئلة مشروعك، ودع الفحص يكشف الفجوات، ثم اخرج بدرجة واضحة
                        وأولويات مرتّبة على الأثر والجهد.
                    </p>
                </div>

                <div class="hero-reference__rule" aria-hidden="true"></div>

                <div class="hero-reference__score">
                    <b>64<span>/100</span></b>
                    <p>درجة نضج على 8 محاور<br><small>مثال توضيحي — ليس نتيجة عميل</small></p>
                </div>

                <div class="hero-reference__action">
                    <p>نحو 10 دقائق<br>ولا تعيد شرح مشروعك مرتين</p>
                    <a class="hero-reference__cta" href="{{ $startUrl }}">{{ $startLabel }}</a>
                    <a class="hero-reference__secondary" href="{{ $learningUrl }}">{{ __('ابدأ تعلم التسويق') }} ←</a>
                    <a class="hero-reference__secondary" href="{{ route('tools.index') }}">{{ __('استكشف التشخيصات') }} ←</a>
                </div>
            </div>

            <div class="container hero-reference__sectors" aria-label="قطاعات التخصص">
                @foreach (\App\Modules\Shared\Sectors\Sector::SPECIALIZED as $index => $sectorKey)
                    @if ($index > 0)<i></i>@endif
                    <a href="{{ route('sectors.show', $sectorKey) }}">{{ \App\Modules\Shared\Sectors\Sector::label($sectorKey) }}</a>
                @endforeach
                <i></i>
                <span>وبقية القطاعات بالمسار الكامل نفسه</span>
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

        <section class="esec" id="why">
            <img class="esec__object" data-section-art="section-why-signals.png"
                 src="{{ asset('assets/design/section-why-signals.png') }}?v=3"
                 alt="" aria-hidden="true" width="1536" height="1024" loading="lazy" decoding="async">

            <div class="container esec__inner">
                <header>
                    <p class="esec__eyebrow">ابدأ من هنا</p>
                    <h2 class="esec__title">هل تصف إحدى هذه الحالات مشروعك؟</h2>
                </header>

                <p class="esec__aside">
                    اختر الأقرب إلى حالتك. من هنا يبدأ التشخيص: أحدّد أين يذهب المال
                    والوقت قبل أن أقترح عليك شيئًا.
                </p>

                <ol class="esec__list">
                    @foreach ($brand['problems'] as $index => $problem)
                        <li class="erow">
                            <x-section-icon :name="$problemIcons[$index] ?? 'compass'" />
                            <span class="erow__num">0{{ $index + 1 }}</span>
                            <h3 class="erow__title">{{ $problem['title'] }}</h3>
                            <p class="erow__desc">{{ $problem['description'] }}</p>
                        </li>
                    @endforeach
                </ol>

                <a class="esec__more" href="{{ $startUrl }}">
                    ابدأ التشخيص الأول <span aria-hidden="true">←</span>
                </a>
            </div>
        </section>

        <section class="outcomes" id="services">
            <img class="outcomes__object" data-section-art="section-services-outcomes.png"
                 src="{{ asset('assets/design/section-services-outcomes.png') }}?v=3"
                 alt="" aria-hidden="true" width="1536" height="1024" loading="lazy" decoding="async">

            <div class="container outcomes__inner">
                <header class="outcomes__head">
                    <p class="outcomes__eyebrow">ما تخرج به</p>
                    <h2 class="outcomes__title">ما الذي سيساعدك على اتخاذ قرار أوضح؟</h2>
                </header>

                <p class="outcomes__aside">
                    لا نبدأ من إعلان ولا من منصة. نبدأ من سؤال: ما أهم شيء تحتاج حلّه الآن؟
                    تخرج بأولويات مرتّبة تنفّذها بنفسك أو تسلّمها لفريقك.
                </p>

                <ol class="outcomes__list">
                    @foreach ($brand['services'] as $index => $service)
                        <li class="outcome">
                            <x-section-icon :name="$serviceIcons[$index] ?? 'tasks'" />
                            <span class="outcome__num">{{ $service['number'] }}</span>
                            <h3 class="outcome__title">{{ $service['title'] }}</h3>
                            <p class="outcome__desc">{{ $service['description'] }}</p>
                        </li>
                    @endforeach
                </ol>

                <a class="outcomes__more" href="{{ route('services') }}">
                    المشكلات والمخرجات كاملة <span aria-hidden="true">←</span>
                </a>
            </div>
        </section>

        <section class="esec" id="method">
            @php($obj = 'assets/design/method-device-exploded.png')
            @if (file_exists(public_path($obj)))
                {{-- يُفعَّل تلقائيًّا حين تصل الصورة: لا تركيب ثانٍ ولا رابط مكسور --}}
                <img class="esec__object esec__object--wide" src="{{ asset($obj) }}?v=3"
                     data-section-art="method-device-exploded.png"
                     alt="" aria-hidden="true" width="864" height="1821" loading="lazy" decoding="async">
            @endif

            <div class="container esec__inner">
                <header>
                    <p class="esec__eyebrow">كيف تصل إلى النتيجة</p>
                    <h2 class="esec__title">من إجاباتك إلى قائمة إصلاح مرتّبة</h2>
                </header>

                <p class="esec__aside">
                    تعطيني معلومات مشروعك مرة واحدة، وتخرج بتشخيص يقول ما يحتاج إجراءً الآن
                    وما ينتظر. وكل رقم يأتي معه أساسه: مِمَّ حُسب، وكم بندًا فُحص،
                    وما وُسم منه فرضية.
                </p>

                <ol class="esec__list">
                    @foreach ($brand['method'] as $index => $step)
                        <li class="erow">
                            <x-section-icon :name="$methodIcons[$index] ?? 'act'" />
                            <span class="erow__num">{{ $step['step'] }}</span>
                            <h3 class="erow__title">{{ $step['title'] }}</h3>
                            <p class="erow__desc">{{ $step['description'] }}</p>
                        </li>
                    @endforeach
                </ol>

                <a class="esec__more" href="{{ route('methodology') }}">
                    منهجية العمل كاملة <span aria-hidden="true">←</span>
                </a>
            </div>
        </section>

        <section class="esec" id="tools">
            <img class="esec__object esec__object--wide esec__object--sectors"
                 data-section-art="content-sectors.png"
                 src="{{ asset('assets/design/content-sectors.png') }}?v=3" alt="" aria-hidden="true"
                 width="1536" height="1024" loading="lazy" decoding="async">
            <div class="container esec__inner">
                <header>
                    <p class="esec__eyebrow">اختر الأولوية</p>
                    <h2 class="esec__title">ما الذي تريد فهمه أو تحسينه الآن؟</h2>
                </header>

                <p class="esec__aside">
                    اختر التحدي الأقرب إلى مشروعك. كل سطر يقول ما يقيسه وكم يستغرق،
                    ثم تنتقل من الأسئلة إلى خطوات تنفّذها أو تسلّمها لفريقك.
                </p>

                <ol class="esec__list">
                    @foreach ($tools as $index => $tool)
                        <li class="erow erow--link">
                            <x-section-icon :name="$toolIcons[$index % count($toolIcons)]" />
                            <span class="erow__num">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="erow__title">
                                <a href="{{ route('tools.show', $tool['key']) }}">{{ $tool['title'] }}</a>
                                @unless ($tool['is_runnable'])
                                    <em class="erow__flag">قريبًا</em>
                                @endunless
                            </h3>
                            <p class="erow__desc">
                                {{ $tool['promise'] ?: $tool['description'] }}
                                @if ($tool['duration_minutes'])
                                    <b class="erow__meta">{{ $tool['duration_minutes'] }} دقائق</b>
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ol>

                <a class="esec__more" href="{{ route('tools.index') }}">
                    كل التشخيصات <span aria-hidden="true">←</span>
                </a>
            </div>
        </section>

        <section class="esec" id="sample">
            @php($obj = 'assets/design/section-sample-report.png')
            @if (file_exists(public_path($obj)))
                <img class="esec__object esec__object--wide" data-section-art="section-sample-report.png"
                     src="{{ asset($obj) }}?v=3"
                     alt="" aria-hidden="true" width="1536" height="1024" loading="lazy" decoding="async">
            @endif

            <div class="container esec__inner">
                <header>
                    <p class="esec__eyebrow">مثال توضيحي — ليس نتيجة عميل</p>
                    <h2 class="esec__title">هكذا تساعدك النتيجة على اتخاذ القرار</h2>
                </header>

                <div class="esec__aside">
                    <p class="esec__score"><b>64<i>/100</i></b><span>درجة نضج على 8 محاور</span></p>
                    <p>ترى درجتك، والفجوات التي وراءها، والخطوة التالية. وكل مصطلح مشروح في موضعه.</p>
                </div>

                <ol class="esec__list">
                    <li class="erow">
                        <x-section-icon name="score" />
                        <span class="erow__num">01</span>
                        <h3 class="erow__title">درجة من 100 ومعها عدد المحاور</h3>
                        <p class="erow__desc">لا رقم بلا أساسه: تعرف من كم محورًا حُسبت، وما نسبة التغطية.</p>
                    </li>
                    <li class="erow">
                        <x-section-icon name="evidence" />
                        <span class="erow__num">02</span>
                        <h3 class="erow__title">«مقيس» مقابل «فرضية»</h3>
                        <p class="erow__desc">ما فُحص من موقعك موسوم مقيسًا، وما بُني على كلامك موسوم فرضية — لا يختلطان.</p>
                    </li>
                    <li class="erow">
                        <x-section-icon name="priority" />
                        <span class="erow__num">03</span>
                        <h3 class="erow__title">الفجوات على محورين</h3>
                        <p class="erow__desc">الأثر والجهد. ابدأ بهذه، وأجّل تلك بلا ندم.</p>
                    </li>
                    <li class="erow">
                        <x-section-icon name="calendar" />
                        <span class="erow__num">04</span>
                        <h3 class="erow__title">كل توصية تصير مهمة لها موعد</h3>
                        <p class="erow__desc">فلا تقف النتيجة عند «فهمت المشكلة».</p>
                    </li>
                </ol>

                <a class="esec__more" href="{{ route('sample-report') }}">
                    نموذج النتيجة كاملًا <span aria-hidden="true">←</span>
                </a>
            </div>
        </section>

        <section class="esec" id="about">
            <img class="esec__object" data-section-art="section-about-career.png"
                 src="{{ asset('assets/design/section-about-career.png') }}?v=3"
                 alt="" aria-hidden="true" width="1536" height="1024" loading="lazy" decoding="async">
            <div class="container esec__inner">
                <header>
                    <p class="esec__eyebrow">من يقف خلف المنهجية</p>
                    <h2 class="esec__title">عني</h2>
                </header>

                <div class="esec__aside">
                    <p>أكثر من 10 سنوات في بناء الحملات وقيادة الفرق وقراءة البيانات. المسار كاملًا بالتواريخ أدناه.</p>
                    <p class="esec__links">
                        <a href="{{ route('profile') }}">السيرة المهنية الكاملة ←</a>
                        <a href="{{ $brand['contact']['linkedin'] }}" target="_blank" rel="noopener noreferrer">LinkedIn ↗</a>
                        <a href="{{ $brand['contact']['x'] }}" target="_blank" rel="noopener noreferrer">X / Twitter ↗</a>
                    </p>
                </div>

                {{-- المسار بالتواريخ: صفوف لا بطاقة زمنية بنقاط ووصلات.
                     التاريخ هو الرقم الضخم لأنه ما تمسحه العين في سيرة مهنية. --}}
                <ol class="esec__list">
                    @foreach ($brand['experience'] as $index => $experience)
                        <li class="erow">
                            <x-section-icon :name="$experienceIcons[$index % count($experienceIcons)]" />
                            <span class="erow__num erow__num--sm">{{ $experience['period'] }}</span>
                            <h3 class="erow__title">{{ $experience['role'] }}</h3>
                            <p class="erow__desc">{{ $experience['company'] }} · {{ $experience['location'] }}</p>
                        </li>
                    @endforeach

                    {{-- التعليم والاعتمادات ومجالات العمل: كانت ثلاث بطاقات جانبية.
                         صارت ثلاثة صفوف بالنمط نفسه — لم يسقط منها محتوى. --}}
                    <li class="erow">
                        <x-section-icon name="education" />
                        <span class="erow__num erow__num--sm">التعليم</span>
                        <h3 class="erow__title">{{ $brand['education'][0]['degree'] }}</h3>
                        <p class="erow__desc">{{ $brand['education'][0]['institution'] }} · {{ $brand['education'][0]['period'] }}</p>
                    </li>
                    <li class="erow">
                        <x-section-icon name="award" />
                        <span class="erow__num erow__num--sm">اعتمادات</span>
                        <h3 class="erow__title">{{ count($brand['credentials']) }} شهادة وتعلّم مستمر</h3>
                        <p class="erow__desc">
                            {{ implode(' · ', array_map(fn ($c) => $c['name'], array_slice($brand['credentials'], 0, 3))) }}
                            <a class="erow__meta" href="{{ route('profile') }}#profile-credentials">استعرضها كلها ←</a>
                        </p>
                    </li>
                    <li class="erow">
                        <x-section-icon name="skills" />
                        <span class="erow__num erow__num--sm">المجالات</span>
                        <h3 class="erow__title">مجالات العمل</h3>
                        <p class="erow__desc">{{ implode(' · ', $brand['skills']) }}</p>
                    </li>
                </ol>
            </div>
        </section>

        <section class="esec" id="principles">
            <img class="esec__object" data-section-art="section-principles-trust.png"
                 src="{{ asset('assets/design/section-principles-trust.png') }}?v=3"
                 alt="" aria-hidden="true" width="1536" height="1024" loading="lazy" decoding="async">
            <div class="container esec__inner">
                <header>
                    <p class="esec__eyebrow">ما ألتزم به</p>
                    <h2 class="esec__title">ما الذي يمكنك أن تتوقعه؟</h2>
                </header>

                <p class="esec__aside">
                    لا أعدك برقم. أعدك بأن تعرف كيف حُسب كل رقم تراه، وما وُسم منه «فرضية»
                    لأنه مبنيّ على كلامك لا على قياس.
                </p>

                <ol class="esec__list">
                    <li class="erow"><x-section-icon name="reason" /><span class="erow__num">01</span><h3 class="erow__title">لا توصية من دون سببها</h3><p class="erow__desc">كل خطوة مقترحة معها الفجوة التي جاءت منها وأثرها المتوقع.</p></li>
                    <li class="erow"><x-section-icon name="decision" /><span class="erow__num">02</span><h3 class="erow__title">القرار يبقى قرارك</h3><p class="erow__desc">التشخيص يرتّب لك الصورة، والدرجة مبنيّة على إجاباتك وعلى ما فُحص من موقعك.</p></li>
                    <li class="erow"><x-section-icon name="followup" /><span class="erow__num">03</span><h3 class="erow__title">لا تشخيص من دون متابعة</h3><p class="erow__desc">ينتهي التشخيص بمهام لها مواعيد ومؤشرات تقيس بها تقدمك.</p></li>
                    <li class="erow"><x-section-icon name="lock" /><span class="erow__num">04</span><h3 class="erow__title">معلوماتك تخصك أنت</h3><p class="erow__desc">أستخدمها لتحليل مشروعك فقط، ولا أعرضها ولا أجعلها مثالًا أمام الناس.</p></li>
                </ol>

                <a class="esec__more" href="{{ route('principles') }}">
                    مبادئ العمل كاملة <span aria-hidden="true">←</span>
                </a>
            </div>
        </section>

        <section class="esec" id="knowledge">
            <img class="esec__object esec__object--wide esec__object--knowledge"
                 data-section-art="content-knowledge.png"
                 src="{{ asset('assets/design/content-knowledge.png') }}?v=3" alt="" aria-hidden="true"
                 width="1536" height="1024" loading="lazy" decoding="async">
            <div class="container esec__inner">
                <header>
                    <p class="esec__eyebrow">المكتبة المعرفية</p>
                    <h2 class="esec__title">محتوى يحول المعرفة إلى خطوات</h2>
                </header>

                <p class="esec__aside">
                    مقالات ودروس ومحاضرات ودورات عملية: افهم المشكلة وطبّق خطوة واضحة
                    يمكنك قياس أثرها.
                </p>

                <ol class="esec__list">
                    @forelse ($knowledge as $index => $item)
                        <li class="erow erow--link">
                            <x-section-icon :name="['article' => 'article', 'lesson' => 'lesson', 'lecture' => 'lecture', 'course' => 'course'][$item->type]" />
                            <span class="erow__num erow__num--sm">{{ ['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'][$item->type] }}</span>
                            <h3 class="erow__title"><a href="{{ route('content.show', $item) }}">{{ $item->title }}</a></h3>
                            <p class="erow__desc">{{ $item->excerpt }}</p>
                        </li>
                    @empty
                        <li class="erow">
                            <x-section-icon name="article" />
                            <span class="erow__num erow__num--sm">النشرة</span>
                            <h3 class="erow__title">{{ $brand['knowledge'][0]['title'] }}</h3>
                            <p class="erow__desc">{{ $brand['knowledge'][0]['description'] }}</p>
                        </li>
                    @endforelse
                </ol>

                <a class="esec__more" href="{{ route('knowledge') }}">
                    صفحة المعرفة <span aria-hidden="true">←</span>
                </a>
            </div>
        </section>

        <section class="esec" id="faq">
            <img class="esec__object" data-section-art="section-faq-conversation.png"
                 src="{{ asset('assets/design/section-faq-conversation.png') }}?v=3"
                 alt="" aria-hidden="true" width="1536" height="1024" loading="lazy" decoding="async">
            <div class="container esec__inner">
                <header>
                    <p class="esec__eyebrow">قبل أن تبدأ</p>
                    <h2 class="esec__title">الأسئلة الشائعة</h2>
                </header>

                <p class="esec__aside">
                    إن لم تجد سؤالك هنا، تواصل مباشرة.
                    <span class="esec__links">
                        <a href="{{ route('faq') }}">صفحة الأسئلة ←</a>
                        <a href="{{ $brand['contact']['whatsapp'] }}" target="_blank" rel="noopener noreferrer">واتساب ↗</a>
                    </span>
                </p>

                {{-- الطيّ يبقى: قائمة أسئلة مفتوحة كلها تُغرق القارئ.
                     لكنه بخطوط شعرية لا ببطاقات، ومفتاحه علامة زائد لا سهم ملوّن. --}}
                <div class="esec__list efaq">
                    @foreach ($brand['faqs'] as $index => $faq)
                        <details class="efaq__item" @if ($index === 0) open @endif>
                            <summary>
                                <x-section-icon name="question" />
                                <span class="erow__num erow__num--sm">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <h3 class="erow__title">{{ $faq['question'] }}</h3>
                            </summary>
                            <p class="erow__desc">{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="esec esec--close" id="diagnosis">
            @php($obj = 'assets/design/section-diagnosis-start.png')
            @if (file_exists(public_path($obj)))
                <img class="esec__object esec__object--wide" data-section-art="section-diagnosis-start.png"
                     src="{{ asset($obj) }}?v=3"
                     alt="" aria-hidden="true" width="1536" height="1024" loading="lazy" decoding="async">
            @endif

            <div class="container esec__inner">
                <header>
                    <p class="esec__eyebrow">أربع خطوات من السؤال إلى القرار</p>
                    <h2 class="esec__title">ابدأ تشخيص مشروعك الآن.</h2>
                </header>

                <div class="esec__aside">
                    <p>ابدأ من دون حساب، أجب عن الأسئلة، ثم أنشئ حسابك لحفظ النتيجة ومتابعة الخطوات.</p>
                    <p class="esec__note">من 7 إلى 10 دقائق · كل خطوة تُحفظ · تعود في أي وقت</p>
                </div>

                <ol class="esec__steps">
                    <li><x-section-icon name="enter" /><b>1</b> ابدأ من دون حساب</li>
                    <li><x-section-icon name="answers" /><b>2</b> أجب عن أسئلة واضحة</li>
                    <li><x-section-icon name="account" /><b>3</b> أنشئ حسابك لحفظ النتيجة</li>
                    <li><x-section-icon name="review" /><b>4</b> راجع أولوياتك ومهامك</li>
                </ol>

                <div class="esec__cta">
                    @if (! auth()->check() && $entryTool !== null)
                        <form method="POST" action="{{ route('try.start', $entryTool['key']) }}">
                            @csrf
                            <button type="submit" class="button button--primary">{{ $startLabel }} <span aria-hidden="true">←</span></button>
                        </form>
                    @else
                        <a class="button button--primary" href="{{ $startUrl }}">{{ $startLabel }} <span aria-hidden="true">←</span></a>
                    @endif
                    <a class="button button--ghost" href="{{ auth()->check() ? route('app.consultations.index') : route('register', ['intent' => 'consultation']) }}">
                        التشخيص الذكي الشامل <span aria-hidden="true">←</span>
                    </a>
                </div>
            </div>
        </section>

    </main>

    @include('partials.site-footer')
@endsection
