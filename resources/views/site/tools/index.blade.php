@extends('layouts.public')
@section('layout', 'marketing')

@section('title', 'ما الذي تريد تشخيصه الآن؟ | خالد سعد')
@section('description', 'اختر التحدي الأقرب إلى مشروعك، وأجب عن أسئلة عن نشاطك، فتخرج بفجواتك وأولوياتك وخطوتك التالية.')

@section('content')
    @include('partials.site-header')

    @php
        $availableTools = collect($tools)->where('is_runnable', true)->values();
        $comingSoonTools = collect($tools)->where('is_runnable', false)->values();
    @endphp

    <main id="main-content">
        <section class="page-hero tools-index-hero">
            <div class="container tools-index-hero__inner">
                <div class="tools-index-hero__copy">
                    <p class="eyebrow">اختر الأولوية</p>
                    <h1>ما الذي تريد فهمه أو تحسينه الآن؟</h1>
                    <p class="page-hero__lead">
                        اختر الحالة الأقرب إلى وضع مشروعك. تجيب عن أسئلة عن نشاطك، ثم تخرج بفجواتك
                        وأولوياتك وخطوة تالية تنفّذها أو تناقشها مع فريقك.
                    </p>
                    <p class="tools-index-hero__sector-note">
                        وإن كان مشروعك في
                        @foreach (\App\Modules\Shared\Sectors\Sector::SPECIALIZED as $sectorKey)
                            <a href="{{ route('sectors.show', $sectorKey) }}">{{ \App\Modules\Shared\Sectors\Sector::label($sectorKey) }}</a>@if (! $loop->last)@if ($loop->remaining === 1) أو @else، @endif @endif
                        @endforeach
                        فداخل كل تشخيص أسئلة وبنود فحص تخص قطاعك وحده.
                    </p>

                    <div class="page-hero__actions">
                        @auth
                            <a class="button button--primary button--large" href="{{ route('app.dashboard') }}">تابع من لوحتك <span aria-hidden="true">←</span></a>
                        @else
                            <a class="button button--primary button--large" href="#التشخيصات-المتاحة">اختر تشخيصك <span aria-hidden="true">←</span></a>
                            <a class="button button--ghost button--large" href="{{ route('login') }}">تسجيل الدخول</a>
                        @endauth
                    </div>
                </div>

                <figure class="tools-index-hero__visual" aria-hidden="true">
                    <img src="{{ asset('assets/design/tools-diagnosis-selector.png') }}?v=1"
                        alt="" width="1536" height="1024" loading="eager" fetchpriority="high" decoding="async">
                </figure>
            </div>
        </section>

        <section class="section catalog-section" id="الحالات">
            <div class="container tools-catalog">
                <section class="tools-catalog-group" aria-labelledby="available-tools-title">
                    <header class="tools-catalog-group__head">
                        <div><p class="eyebrow">متاح الآن</p><h2 id="available-tools-title">ابدأ من المشكلة الأقرب إلى مشروعك</h2></div>
                        <p>{{ $availableTools->count() }} تشخيصات جاهزة، وكل واحد منها يوضح مدته وما الذي ستخرج به.</p>
                    </header>
                    <div class="catalog-grid public-card-grid tools-catalog-grid tools-catalog-grid--available" id="التشخيصات-المتاحة">
                        @foreach ($availableTools as $tool)
                            @include('site.tools._catalog-card', ['tool' => $tool, 'featured' => $loop->first])
                        @endforeach
                    </div>
                </section>

                @if ($comingSoonTools->isNotEmpty())
                    <section class="tools-catalog-group tools-catalog-group--soon" aria-labelledby="coming-tools-title">
                        <header class="tools-catalog-group__head">
                            <div><p class="eyebrow">قريبًا</p><h2 id="coming-tools-title">تشخيصات تُستكمل تباعًا</h2></div>
                            <p>يمكنك قراءة مخرج كل تشخيص الآن، وتظهر إمكانية البدء فور اكتماله.</p>
                        </header>
                        <div class="catalog-grid public-card-grid tools-catalog-grid tools-catalog-grid--soon">
                            @foreach ($comingSoonTools as $tool)
                                @include('site.tools._catalog-card', ['tool' => $tool])
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </section>

        <section class="section how-section">
            <div class="container">
                <x-section-heading
                    eyebrow="أربع خطوات"
                    title="كيف تصل من السؤال إلى الخطوة التالية؟"
                    description="تبدأ بالتحدي الأقرب إلى مشروعك، ثم تحفظ النتيجة وتتابع أولوياتك من مكان واحد."
                />
                <ol class="journey-steps">
                    <li><x-section-icon name="enter" /><span>01</span><h3>ابدأ مباشرة</h3><p>ابدأ التشخيص من دون إنشاء حساب ومن دون بطاقة دفع.</p></li>
                    <li><x-section-icon name="answers" /><span>02</span><h3>صف واقع مشروعك</h3><p>أجب عن أسئلة عن نشاطك، وبجانب كل سؤال سبب طرحه وأثره في النتيجة.</p></li>
                    <li><x-section-icon name="account" /><span>03</span><h3>احفظ ما أنجزته</h3><p>أنشئ حسابك بعد التجربة، وتنتقل إجاباتك معك كما هي.</p></li>
                    <li><x-section-icon name="review" /><span>04</span><h3>رتّب أولوياتك</h3><p>راجع فجواتك وقائمة الإصلاح، وحوّل ما تختاره إلى مهام لها مواعيد.</p></li>
                </ol>

                <div class="cta-band">
                        <p>ابدأ أولًا، ثم قرر إن كنت تريد حفظ النتيجة. إجاباتك تنتقل إلى حسابك كما هي.</p>
                    @auth
                        <a class="button button--primary" href="{{ route('app.dashboard') }}">افتح لوحتك</a>
                    @else
                        <a class="button button--primary" href="{{ route('register') }}">أنشئ حسابك</a>
                    @endauth
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer')
@endsection
