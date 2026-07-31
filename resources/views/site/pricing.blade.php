@extends('layouts.public')
@section('layout', 'marketing')

@section('title', 'الأسعار — ابدأ مجانًا وادفع حين ترى القيمة | خالد سعد')
@section('description', 'تشخيص مجاني يريك درجة نضج تسويقك وأهم فجواتك، وخطط مدفوعة للتقرير الكامل والمتابعة والتنبيهات. الأسعار واضحة بلا مفاجآت.')

@php
    // FAQ تُكتب مرة واحدة وتُقدَّم للقارئ وللمحركات معًا (JSON-LD أدناه).
    $faq = [
        ['q' => 'هل أحتاج بطاقة دفع للبدء؟', 'a' => 'لا. التشخيص الأولي مجاني بالكامل: تجيب عن أسئلة واضحة وتحصل على درجتك وأهم فجواتك دون أي بيانات دفع.'],
        ['q' => 'ما الفرق بين المجاني والمدفوع؟', 'a' => 'المجاني يريك أين تقف وأهم ما ينقصك بالاسم. المدفوع يعطيك التقرير الكامل بالأدلة والتوصيات المرتبة بالأثر والجهد، والمتابعة الزمنية، والتنبيهات حين تتغير درجتك أو يتحرك منافسك.'],
        ['q' => 'هل أستطيع الإلغاء متى شئت؟', 'a' => 'نعم. الاشتراك شهري وتلغيه من لوحتك بنقرة، وتبقى خدمتك فعّالة حتى نهاية الفترة المدفوعة.'],
        ['q' => 'أنا وكالة أدير أكثر من عميل — ماذا يناسبني؟', 'a' => 'خطة الوكالة تعطيك جدول محفظة عملائك ودرجاتهم واتجاهها، وتصدير التقارير بعلامتك. راسلنا من صفحة التواصل لترتيب ما يناسب حجمك.'],
    ];
@endphp

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="hero hero--slim">
            <div class="container">
                <p class="eyebrow">أسعار واضحة · بلا مفاجآت</p>
                <h1>ابدأ مجانًا، وادفع حين ترى القيمة بعينك</h1>
                <p class="hero-lead">
                    التشخيص الأولي مجاني ويريك درجتك وأهم فجواتك. الخطط المدفوعة
                    تفتح التقرير الكامل والمتابعة والتنبيهات — كل خطة تقول بالضبط ما الذي تحصل عليه.
                </p>
            </div>
        </section>

        <section class="section" aria-labelledby="plans-title">
            <div class="container">
                <h2 id="plans-title" class="sr-only">الخطط المتاحة</h2>

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
                                {{ $plan['price'] === 0 ? 'ابدأ الآن مجانًا' : 'اشترك من لوحتك' }}
                            </a>
                        </article>
                    @empty
                        <p>الخطط تُجهَّز حاليًا — ابدأ بالتشخيص المجاني وسنعلمك حين تفتح.</p>
                    @endforelse
                </div>

                <p class="pricing-note">
                    كل الأسعار بالريال السعودي. الاشتراك يُدار من لوحتك: ترقية أو إلغاء بنقرة،
                    ولا يُخصم منك شيء دون أن تراه أولًا.
                </p>
            </div>
        </section>

        <section class="section section--soft" id="faq" aria-labelledby="faq-title">
            <div class="container narrow">
                <h2 id="faq-title" class="section-heading">أسئلة تسبق قرارك</h2>
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
