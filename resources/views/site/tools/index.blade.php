@extends('layouts.public')

@section('title', 'بماذا نساعدك؟ | خالد سعد')
@section('description', 'اختر المشكلة التي تواجهك الآن في تسويق مشروعك، وخذ خطة واضحة بخطوات تنفذها هذا الأسبوع.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner">
                <p class="eyebrow">ابدأ من مشكلتك</p>
                <h1>أين المشكلة عندك الآن؟</h1>
                <p class="page-hero__lead">
                    اختَر الحالة الأقرب لوضعك، ونمشي معك خطوة خطوة: نسألك أسئلة تعرف إجابتها،
                    ونقدّم لك إرشادات واضحة يمكنك تنفيذها — لا تقارير تقرؤها ثم تنساها.
                </p>

                <div class="page-hero__actions">
                    @auth
                        <a class="button button--primary button--large" href="{{ route('app.dashboard') }}">تابع من حيث وقفت <span aria-hidden="true">←</span></a>
                    @else
                        <a class="button button--primary button--large" href="#الحالات">اختر حالتك وابدأ <span aria-hidden="true">←</span></a>
                        <a class="button button--ghost button--large" href="{{ route('login') }}">عندي حساب</a>
                    @endauth
                </div>
            </div>
        </section>

        <section class="section catalog-section" id="الحالات">
            <div class="container">
                <div class="catalog-grid">
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
                                اعرف التفاصيل <b aria-hidden="true">←</b>
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
                    eyebrow="قبل أن تبدأ"
                    title="كيف تمشي معك الخطوات؟"
                    description="أربع خطوات فقط، وكل خطوة محفوظة ويمكنك العودة إليها في أي وقت."
                />
                <ol class="journey-steps">
                    <li><span>01</span><h3>تبدأ فورًا</h3><p>بدون حساب وبدون بطاقة — تضغط وتبدأ في نفس اللحظة.</p></li>
                    <li><span>02</span><h3>أسئلة تعرف إجابتها</h3><p>أسئلة عن شغلك اليومي، وبجانب كل سؤال نقول لك لماذا نسأله.</p></li>
                    <li><span>03</span><h3>تحفظ نتيجتك</h3><p>حساب مجاني في دقيقة، وكل ما كتبته ينتقل معك كما هو.</p></li>
                    <li><span>04</span><h3>خطة يمكنك تنفيذها</h3><p>أين المشكلة بالتحديد، وما الذي تبدأ به، ومهام لها مواعيد تتابعها.</p></li>
                </ol>

                <div class="cta-band">
                        <p>تجرّب أولًا وتقرر بعدها. ما تكتبه لا يضيع، ينتقل معك حين تنشئ حسابك.</p>
                    @auth
                        <a class="button button--primary" href="{{ route('app.dashboard') }}">ادخل على مشروعك</a>
                    @else
                        <a class="button button--primary" href="{{ route('register') }}">ابدأ الآن</a>
                    @endauth
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer')
@endsection
