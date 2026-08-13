@extends('layouts.public')
@section('layout', 'marketing')

@section('title', 'القطاعات الثلاثة: التعليم والتجارة الإلكترونية والعقارات | خالد سعد')
@section('description', 'نتخصص في ثلاثة قطاعات: التعليم والتجارة الإلكترونية والعقارات. لكل قطاع أسئلته وبنود فحصه ومؤشراته. وبقية القطاعات تمرّ بالمسار الكامل نفسه.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="hero hero--slim">
            <div class="container">
                <p class="eyebrow">ثلاثة قطاعات</p>
                <h1>في أي قطاع تعمل؟</h1>
                <p class="hero-lead">
                    أسألك أسئلة وأفحص بنودًا وأعرض مؤشرات تخص قطاعك وحده. اختر قطاعك لترى
                    ما الذي يتغيّر فعلًا، وبقية القطاعات تمرّ بالمسار الكامل نفسه.
                </p>
            </div>
        </section>

        <section class="section" aria-labelledby="sectors-title">
            <div class="container">
                {{-- كان هذا العنوان `sr-only`، فتقفز الصفحة بصريًّا من h1 إلى ثلاث h3
                     بلا عنوان يفصل الكتلة. الإخفاء البصري لعنوان القسم الوحيد في
                     الصفحة يخدم قارئ الشاشة ويترك القارئ البصري بلا مرساة. --}}
                <h2 id="sectors-title" class="section-title">القطاعات الثلاثة</h2>

                {{--
                    الثلاثة في شبكة واحدة بلا ترتيب أفضلية: أي إبراز لواحد منها
                    يُقرأ أن الاثنين الآخرين حاشية — وهو ما كان يفعله رابط التنقل
                    حين كان يذهب إلى التعليم وحده.
                --}}
                <div class="sector-grid">
                    @foreach ($sectors as $item)
                        @php($profile = $item['profile'])
                        <article class="sector-card">
                            {{-- الأيقونة من عائلة الأيقونات الواحدة، ومفتاحها من `Sector`.
                                 كانت هنا علامة تأخذ أول حرف من التسمية، فأخرجت «ا» في
                                 البطاقات الثلاث كلها — ألف التعريف. --}}
                            <span class="sector-card__mark" aria-hidden="true">
                                @include('site.content._icon', ['name' => \App\Modules\Shared\Sectors\Sector::icon($item['sector'])])
                            </span>
                            <h3 class="sector-card__name">{{ $item['label'] }}</h3>
                            <p class="sector-card__audience">{{ $profile['audience'] }}</p>
                            <p class="sector-card__pain">{{ $profile['pain'] }}</p>

                            {{-- الأرقام من المحرّك لا من ادّعاء: تنقص إن نقص المنتج. --}}
                            <ul class="sector-card__facts">
                                {{-- التمييز يتبع العدد: ٣–١٠ جمعٌ، و١١ فأكثر مفردٌ منصوب. --}}
                                @if ($item['questions']['count'] > 0)
                                    <li>
                                        <strong>{{ \App\Support\Presentation\Num::int($item['questions']['count']) }}</strong>
                                        {{ $item['questions']['count'] <= 10 ? 'أسئلة إضافية' : 'سؤالًا إضافيًّا' }}
                                        داخل
                                        {{ \App\Modules\Shared\Text\ArabicText::counted($item['questions']['tools'], 'تشخيصًا', 'تشخيصات', 'تشخيصين') }}
                                    </li>
                                @else
                                    {{-- الفجوة تُعلن ولا تُخفى (§٤.٣). كان الشرط يحذف السطر
                                         صامتًا عند الصفر، فتنهار البطاقة إلى حقيقتين شاحبتين
                                         ولا يلاحظ أحد أن أقوى برهان على التخصص غائب. --}}
                                    <li class="sector-card__facts-gap">الأسئلة القطاعية لم تُنشر بعد</li>
                                @endif
                                @if ($item['kpis'] !== [])
                                    <li>
                                        <strong>{{ \App\Support\Presentation\Num::int(count($item['kpis'])) }}</strong>
                                        {{ count($item['kpis']) <= 10 ? 'مؤشرات' : 'مؤشرًا' }} تتصدّر لوحتك
                                    </li>
                                @endif
                                {{-- الاسم التقني لنوع Schema يبقى لصفحة القطاع: جمهور هذه
                                     البطاقة مدرسةٌ ومكتبُ عقار، و«(Course)» لا يقول لهما شيئًا. --}}
                                <li>فحص بيانات {{ $item['schema']['label'] }} المنظَّمة</li>
                            </ul>

                            {{-- البطاقة كلها هي هدف النقر عبر `::after` الممتد: كان الهدف
                                 الوحيد نصًّا بارتفاع 27px (الحد الأدنى 44px)، بينما التحويم
                                 يرفع البطاقة كلها فيَعِد بأنها قابلة للنقر ثم لا تستجيب. --}}
                            <a class="sector-card__link" href="{{ route('sectors.show', $item['sector']) }}">
                                ما الذي يتغيّر في {{ $item['label'] }}
                                <span aria-hidden="true">←</span>
                            </a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section" aria-labelledby="others-title">
            <div class="container">
                <p class="eyebrow">وبقية القطاعات</p>
                <h2 id="others-title" class="section-title">لست في هذه الثلاثة؟</h2>
                <p>
                    تمرّ بالمسار الكامل نفسه: الأسئلة العامة وبنود الفحص العامة ودرجة النضج
                    والتقرير وقائمة الإصلاح. ما ينقصك هو العمق القطاعي وحده، وأقولها لك
                    صراحةً بدل أن أدّعي تخصصًا لم يُبنَ.
                </p>
                <div class="hero-actions">
                    <a class="button button--primary button--large" href="{{ route('register') }}">
                        ابدأ تشخيص نشاطك
                        <span aria-hidden="true">←</span>
                    </a>
                    <a class="button button--ghost button--large" href="{{ route('tools.index') }}">اطّلع على التشخيصات</a>
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer')
@endsection
