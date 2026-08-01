@extends('layouts.public')
@section('layout', 'marketing')

@php
    $profile = $capabilities['profile'];
    $label = $capabilities['label'];
@endphp

@section('title', 'تشخيص تسويقي متخصص في '.$label.' | خالد سعد')
@section('description', $profile['pain'].' '.$profile['promise'])

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="hero hero--slim">
            <div class="container">
                <p class="eyebrow">تخصص قطاعي · {{ $label }}</p>
                {{-- العنوان يبدأ من وجعه لا من قدراتنا: من يقرأ عن نفسه يكمل. --}}
                <h1>{{ $profile['pain'] }}</h1>
                <p class="hero-lead">{{ $profile['promise'] }}</p>
                <p class="muted">لـ{{ $profile['audience'] }}.</p>

                <div class="hero-actions">
                    <a class="button button--primary button--large" href="{{ route('register') }}">
                        ابدأ تشخيص نشاطك
                        <span aria-hidden="true">←</span>
                    </a>
                    <a class="button button--ghost button--large" href="{{ route('pricing') }}">اطّلع على الأسعار</a>
                </div>
            </div>
        </section>

        <section class="section" aria-labelledby="knows-title">
            <div class="container">
                <p class="eyebrow">ما نعرفه عن قطاعك</p>
                <h2 id="knows-title" class="section-title">لن نسألك عن هذا — نعرفه مسبقًا</h2>
                <p class="muted">هذه المعرفة مكتوبة في المحرّك، وتظهر في أسئلتك وفحوصك ومؤشراتك:</p>
                <ul class="bullets">
                    @foreach ($profile['knows'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        </section>

        @if ($capabilities['questions']['count'] > 0)
            <section class="section" aria-labelledby="questions-title">
                <div class="container">
                    <p class="eyebrow">أسئلتك أنت</p>
                    <h2 id="questions-title" class="section-title">
                        نسألك {{ \App\Support\Presentation\Num::int($capabilities['questions']['count']) }}
                        {{ $capabilities['questions']['count'] == 2 ? 'سؤالًا إضافيين' : 'سؤالًا إضافيًّا' }}
                        لأنك في {{ $label }}
                    </h2>
                    <p class="muted">
                        {{-- الرقم من الحقول المبذورة لا من ادّعاء (§٤.٢): يتغيّر بتغيّر المنتج. --}}
                        تظهر داخل
                        {{ \App\Support\Presentation\Num::int($capabilities['questions']['tools']) }}
                        من تشخيصاتك حين تختار قطاعك، ولا تظهر لغيرك. منها:
                    </p>
                    <ul class="bullets">
                        @foreach ($capabilities['questions']['samples'] as $sample)
                            <li>{{ $sample }}</li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        <section class="section" aria-labelledby="readiness-title">
            <div class="container">
                <p class="eyebrow">الجاهزية للذكاء الاصطناعي</p>
                <h2 id="readiness-title" class="section-title">هل تظهر في إجابات النماذج؟</h2>
                <p>
                    نفحص موقعك كما تقرأه النماذج، ونسألها أسئلة عميلك بلسانه، ونعدّ كم مرة
                    ذُكرت أنت ومن ظهر بدلًا منك. هذه أمثلة من الأسئلة التي نسألها عن قطاعك:
                </p>
                <ul class="bullets">
                    @foreach ($capabilities['buyer_questions'] as $question)
                        <li>«{{ $question }}»</li>
                    @endforeach
                </ul>
                <p class="muted">
                    ونفحص بيانات {{ $capabilities['schema']['label'] }} المنظَّمة في موقعك
                    (<span dir="ltr">{{ implode('، ', $capabilities['schema']['types']) }}</span>)،
                    ونعطيك القصاصة الجاهزة للصق إن كانت ناقصة.
                </p>
            </div>
        </section>

        @if ($capabilities['kpis'] !== [])
            <section class="section" aria-labelledby="kpis-title">
                <div class="container">
                    <p class="eyebrow">ما الذي تتابعه</p>
                    <h2 id="kpis-title" class="section-title">مؤشرات قطاعك تظهر أولًا في لوحتك</h2>
                    <p class="muted">نماذج جاهزة تختار منها بضغطة، ويشرح كلٌّ منها ما يقيسه:</p>
                    <div class="table-scroll">
                        <table class="data-table">
                            <thead><tr><th>المؤشر</th><th>ما الذي يقيسه</th></tr></thead>
                            <tbody>
                                @foreach ($capabilities['kpis'] as $kpi)
                                    <tr>
                                        <td><strong>{{ $kpi['name'] }}</strong> <small class="muted">{{ $kpi['unit'] }}</small></td>
                                        <td>{{ $kpi['measures'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

        <section class="section" aria-labelledby="honest-title">
            <div class="container">
                <p class="eyebrow">وضوح قبل الوعود</p>
                <h2 id="honest-title" class="section-title">ما لا نقوله لك</h2>
                {{--
                    الصدق هنا ليس تواضعًا بل منتج: §٤ تمنع عرض الفرضية حقيقةً،
                    والصفحة التي تبيع بوعد لا يفي به المحرّك تُسقط الثقة عند أول تقرير.
                --}}
                <ul class="bullets">
                    <li>لا نعدك بعدد عملاء ولا بنسبة نمو — لا أحد يستطيع ذلك بصدق قبل أن يرى نشاطك.</li>
                    <li>ما نستنتجه يظهر في تقريرك موسومًا «فرضية»، وما نرصده يظهر بدليله من موقعك أو إجاباتك.</li>
                    <li>معيار القطاع لا يظهر إلا بعد قياس خمسة أنشطة على الأقل فيه — ومتوسط من نشاطين صدفة لا مرجع.</li>
                    <li>القطاعات خارج الثلاثة تُخدم بالمسار الكامل نفسه، لكن بلا هذا العمق القطاعي.</li>
                </ul>
            </div>
        </section>

        <section class="section" aria-labelledby="others-title">
            <div class="container">
                <h2 id="others-title" class="section-title">قطاعاتنا الأخرى</h2>
                <div class="hero-actions">
                    @foreach ($otherSectors as $other)
                        <a class="button button--ghost" href="{{ route('sectors.show', $other) }}">
                            {{ \App\Modules\Shared\Sectors\Sector::label($other) }}
                        </a>
                    @endforeach
                    <a class="button button--ghost" href="{{ route('sectors.index') }}">قارن الثلاثة</a>
                    <a class="button button--ghost" href="{{ route('tools.index') }}">كل التشخيصات</a>
                </div>
            </div>
        </section>

        <section class="section" aria-labelledby="cta-title">
            <div class="container">
                <h2 id="cta-title" class="section-title">ابدأ بمعرفة أين تقف</h2>
                <p class="muted">التشخيص الأولي مجاني ولا يطلب بطاقة دفع.</p>
                <a class="button button--primary button--large" href="{{ route('register') }}">
                    ابدأ تشخيص نشاطك
                    <span aria-hidden="true">←</span>
                </a>
            </div>
        </section>
    </main>

    @include('partials.site-footer')
@endsection
