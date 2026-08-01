@extends('layouts.public')
@section('layout', 'marketing')

@section('title', 'الأسعار — ماذا يفتح لك كل مستوى | خالد سعد')
@section('description', 'التشخيص الأول مجاني: درجتك وأكبر ثلاث فجوات بالاسم. الخطط المدفوعة تفتح التقرير الكامل بأدلته، وقائمة الإصلاح، ومتابعة درجتك مع الوقت.')

@php
    /*
     * FAQ تُكتب مرة واحدة وتُقدَّم للقارئ وللمحركات معًا (JSON-LD أدناه).
     *
     * كل جواب هنا يصف ما يفعله النظام فعلًا لا ما نودّ أن يفعله: الإلغاء
     * يُوصف كتبديل خطة من صفحة الأرصدة لأن هذا هو المسار المبني، وقدرات
     * الوكالة تُسمّى بأسماء شاشاتها. الوعد الذي لا شاشة خلفه يُقاس عليه
     * تصديق الزائر لبقية الصفحة.
     */
    $faq = [
        ['q' => 'هل أحتاج بطاقة دفع للبدء؟', 'a' => 'لا. التشخيص الأول مجاني: تجيب عن أسئلة عن نشاطك، فتخرج بدرجتك وأكبر ثلاث فجوات بالاسم — من دون أي بيانات دفع.'],
        ['q' => 'ما الذي يفتحه المدفوع فوق المجاني؟', 'a' => 'المجاني يقول أين تقف ويسمّي أكبر ثلاث فجوات دون حلّها. المدفوع يفتح التقرير الكامل: لكل نتيجة دليلها ودرجة يقينها، وقائمة إصلاح مرتّبة على الأثر والجهد، ومتابعة درجتك مع الوقت، ونبض أسبوعي يقول ما تغيّر وما تأخّر.'],
        ['q' => 'كيف أوقف اشتراكي أو أغيّر خطتي؟', 'a' => 'من صفحة «الأرصدة والخطط» في لوحتك. تبدّل إلى خطة أخرى أو تعود إلى المجانية، وخطتك الحالية تبقى فعّالة حتى نهاية الفترة المدفوعة — والتاريخ معروض أمامك في الصفحة نفسها.'],
        ['q' => 'أدير عملاء في وكالة — ما الذي يخدمني؟', 'a' => 'ميزات الوكالة تفتح جدول محفظة عملائك ودرجة كل مشروع، وموجز وكالة لكل مشروع تصدّره PDF أو تشاركه برابط. راسلنا عبر واتساب من تذييل الصفحة لترتيب ما يناسب عدد عملائك.'],
    ];
@endphp

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="hero hero--slim">
            <div class="container">
                <p class="eyebrow">الأسعار</p>
                <h1>ماذا يفتح لك كل مستوى؟</h1>
                <p class="hero-lead">
                    التشخيص الأول مجاني: تخرج بدرجتك وأكبر ثلاث فجوات بالاسم.
                    المدفوع يفتح التقرير الكامل بأدلته، وقائمة الإصلاح، ومتابعة درجتك مع الوقت.
                </p>
            </div>
        </section>

        <section class="section" aria-labelledby="plans-title">
            <div class="container">
                <h2 id="plans-title" class="sr-only">الخطط</h2>

                <div class="pricing-grid">
                    @forelse ($plans as $plan)
                        <article @class(['pricing-card', 'pricing-card--featured' => $plan['price'] > 0 && $loop->iteration === 2])>
                            <h3>{{ $plan['name'] }}</h3>
                            <p class="pricing-card__price">
                                @if ($plan['price'] === 0)
                                    <strong>مجانًا</strong>
                                @else
                                    <strong>{{ \App\Support\Presentation\Num::int($plan['price']) }}</strong>
                                    <span>ريال / {{ $plan['interval'] === 'yearly' ? 'سنة' : 'شهر' }}</span>
                                @endif
                            </p>

                            <ul class="bullets">
                                <li>{{ \App\Support\Presentation\Num::int($plan['monthly_credits']) }} رصيد تشخيص شهريًا</li>
                                <li>حتى {{ \App\Support\Presentation\Num::int($plan['project_limit']) }} {{ $plan['project_limit'] === 1 ? 'مشروع' : 'مشاريع' }}</li>
                                @foreach ($plan['features'] as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>

                            <a class="button {{ $plan['price'] === 0 ? 'button--ghost' : 'button--primary' }}"
                                href="{{ auth()->check() ? route('app.billing') : route('register') }}">
                                {{ $plan['price'] === 0 ? 'ابدأ تشخيصك المجاني' : 'اشترك من لوحتك' }}
                            </a>
                        </article>
                    @empty
                        {{-- الغياب يُقال كما هو، لا يُملأ بوعد موعد لم يُحدَّد (§٤.٣). --}}
                        <p>لا توجد خطط معروضة الآن. ابدأ بالتشخيص المجاني، والخطط تظهر هنا حين تُفتح.</p>
                    @endforelse
                </div>

                <p class="pricing-note">
                    كل الأسعار بالريال السعودي. تدير خطتك من صفحة «الأرصدة والخطط» في لوحتك:
                    تبدّل بين الخطط، وترى رصيدك وسجل مدفوعاتك وتاريخ انتهاء فترتك الحالية.
                </p>
            </div>
        </section>

        <section class="section section--soft" id="faq" aria-labelledby="faq-title">
            <div class="container narrow">
                <h2 id="faq-title" class="section-heading">أسئلة قبل أن تشترك</h2>
                @foreach ($faq as $item)
                    <details class="faq-item" @if ($loop->first) open @endif>
                        <summary>{{ $item['q'] }}</summary>
                        <p>{{ $item['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    </main>

    @include('partials.site-footer')
@endsection

@push('head')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($faq)->map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => 'تشخيص التسويق الرقمي — خالد سعد',
            'provider' => ['@type' => 'Person', 'name' => config('brand.name')],
            'areaServed' => 'SA',
            'offers' => $plans->map(fn ($plan) => [
                '@type' => 'Offer',
                'name' => $plan['name'],
                'price' => $plan['price'],
                'priceCurrency' => 'SAR',
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush
