@extends('layouts.public')
@section('layout', 'marketing')

@section('title', 'قطاعاتنا: التعليم والتجارة الإلكترونية والعقارات | خالد سعد')
@section('description', 'نتخصص في ثلاثة قطاعات: التعليم والتجارة الإلكترونية والعقارات. لكل قطاع أسئلته وبنود فحصه ومؤشراته. وبقية القطاعات تمرّ بالمسار الكامل نفسه.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="hero hero--slim">
            <div class="container">
                <p class="eyebrow">ثلاثة قطاعات</p>
                <h1>في أي قطاع تعمل؟</h1>
                <p class="hero-lead">
                    نسألك أسئلة ونفحص بنودًا ونعرض مؤشرات تخص قطاعك وحده. اختر قطاعك لترى
                    ما الذي يتغيّر فعلًا، وبقية القطاعات تمرّ بالمسار الكامل نفسه.
                </p>
            </div>
        </section>

        <section class="section" aria-labelledby="sectors-title">
            <div class="container">
                <h2 id="sectors-title" class="sr-only">القطاعات الثلاثة</h2>

                {{--
                    الثلاثة في شبكة واحدة بلا ترتيب أفضلية: أي إبراز لواحد منها
                    يُقرأ أن الاثنين الآخرين حاشية — وهو ما كان يفعله رابط التنقل
                    حين كان يذهب إلى التعليم وحده.
                --}}
                <div class="sector-grid">
                    @foreach ($sectors as $item)
                        @php($profile = $item['profile'])
                        <article class="sector-card">
                            <span class="sector-card__mark" aria-hidden="true">{{ mb_substr($item['label'], 0, 1) }}</span>
                            <h3 class="sector-card__name">{{ $item['label'] }}</h3>
                            <p class="sector-card__audience">{{ $profile['audience'] }}</p>
                            <p class="sector-card__pain">{{ $profile['pain'] }}</p>

                            {{-- الأرقام من المحرّك لا من ادّعاء: تنقص إن نقص المنتج. --}}
                            <ul class="sector-card__facts">
                                @if ($item['questions']['count'] > 0)
                                    <li>
                                        <strong>{{ \App\Support\Presentation\Num::int($item['questions']['count']) }}</strong>
                                        سؤال إضافي داخل
                                        {{ \App\Support\Presentation\Num::int($item['questions']['tools']) }}
                                        تشخيصًا
                                    </li>
                                @endif
                                @if ($item['kpis'] !== [])
                                    <li><strong>{{ \App\Support\Presentation\Num::int(count($item['kpis'])) }}</strong> مؤشرات تتصدّر لوحتك</li>
                                @endif
                                <li>
                                    فحص بيانات {{ $item['schema']['label'] }} المنظَّمة
                                    <span dir="ltr" class="muted">({{ $item['schema']['types'][0] }})</span>
                                </li>
                            </ul>

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
                    والتقرير وقائمة الإصلاح. ما ينقصك هو العمق القطاعي وحده، ونقولها لك
                    صراحةً بدل أن ندّعي تخصصًا لم يُبنَ.
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
