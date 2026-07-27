@extends('layouts.public')
@section('layout', 'marketing')

@section('title', 'اختر ما تريد تحسينه في مشروعك | خالد سعد')
@section('description', 'اختر التحدي الأقرب إلى مشروعك، واحصل على تشخيص يوضح الفجوات والأولويات والخطوة التالية.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner">
                <p class="eyebrow">ابدأ من التحدي الأهم</p>
                <h1>ما الذي تريد فهمه أو تحسينه الآن؟</h1>
                <p class="page-hero__lead">
                    اختر الحالة الأقرب إلى وضع مشروعك. ستجيب عن أسئلة واضحة، ثم تحصل على أولويات
                    وخطوات يمكنك تنفيذها أو مناقشتها مع فريقك ووكالتك.
                </p>

                <div class="page-hero__actions">
                    @auth
                        <a class="button button--primary button--large" href="{{ route('app.dashboard') }}">تابع من لوحة التحكم <span aria-hidden="true">←</span></a>
                    @else
                        <a class="button button--primary button--large" href="#الحالات">اختر التحدي وابدأ <span aria-hidden="true">←</span></a>
                        <a class="button button--ghost button--large" href="{{ route('login') }}">تسجيل الدخول</a>
                    @endauth
                </div>
            </div>
        </section>

        <section class="section catalog-section" id="الحالات">
            <div class="container">
                <div class="catalog-grid public-card-grid">
                    @foreach ($tools as $tool)
                        <a href="{{ route('tools.show', $tool['key']) }}" @class(['catalog-card', 'catalog-card--soon' => ! $tool['is_runnable']])>
                            <div class="catalog-card__head">
                                <span class="catalog-card__category">{{ $tool['category'] }}</span>
                                @unless ($tool['is_runnable'])
                                    <span class="pill pill--soon">قريبًا</span>
                                @endunless
                            </div>

                            @if ($tool['pain'])
                                <p class="catalog-card__pain">«{{ $tool['pain'] }}»</p>
                            @endif

                            <h2>{{ $tool['title'] }}</h2>
                            <p class="catalog-card__desc">{{ $tool['promise'] ?: $tool['description'] }}</p>

                            <span class="catalog-card__link">
                                اعرف ما ستحصل عليه <b aria-hidden="true">←</b>
                                @if ($tool['duration_minutes'])
                                    <em>{{ $tool['duration_minutes'] }} دقائق تقريبًا</em>
                                @endif
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section how-section">
            <div class="container">
                <x-section-heading
                    eyebrow="رحلة واضحة"
                    title="كيف تصل من السؤال إلى الخطوة التالية؟"
                    description="تبدأ بالتحدي الأقرب إلى مشروعك، ثم تحفظ النتيجة وتتابع أولوياتك من مكان واحد."
                />
                <ol class="journey-steps">
                    <li><span>01</span><h3>ابدأ مباشرة</h3><p>ابدأ التشخيص من دون إنشاء حساب أو إدخال بطاقة دفع.</p></li>
                    <li><span>02</span><h3>صف واقع مشروعك</h3><p>أجب عن أسئلة مرتبطة بعملك، واعرف سبب كل سؤال وأثره في النتيجة.</p></li>
                    <li><span>03</span><h3>احفظ ما أنجزته</h3><p>أنشئ حسابك بعد التجربة، وستنتقل إجاباتك معك كما هي.</p></li>
                    <li><span>04</span><h3>رتّب أولوياتك</h3><p>راجع الفجوات والخطوات المقترحة، وحوّل ما تختاره إلى مهام قابلة للمتابعة.</p></li>
                </ol>

                <div class="cta-band">
                        <p>ابدأ أولًا، ثم قرر إن كنت تريد حفظ النتيجة. ستنتقل إجاباتك إلى حسابك كما هي.</p>
                    @auth
                        <a class="button button--primary" href="{{ route('app.dashboard') }}">افتح لوحة مشروعك</a>
                    @else
                        <a class="button button--primary" href="{{ route('register') }}">أنشئ حسابك</a>
                    @endauth
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer')
@endsection
