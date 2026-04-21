@extends('layouts.marketing')

@section('content')
@php
    $pathTones = ['path-tone-primary', 'path-tone-teal', 'path-tone-gold', 'path-tone-rose', 'path-tone-violet'];
@endphp

<section class="section-lg internal-page-hero">
    <div class="site-container">
        <div class="two-col-wide internal-page-layout">
            <div class="reveal-left">
                <x-marketing.section-badge :text="$eyebrow" />

                <h1 class="heading-lg mb-5">{{ $title }}</h1>
                <p class="text-body-lg mb-6">{{ $description }}</p>

                <div class="page-actions mb-6">
                    @foreach ($actions as $action)
                        <a href="{{ $action['href'] }}" class="btn {{ $loop->first ? 'btn-primary' : 'btn-secondary' }} btn-lg">
                            {{ $action['label'] }}
                        </a>
                    @endforeach
                </div>

                <div class="page-inline-notes">
                    <div class="page-inline-note">
                        <span class="page-inline-note-label">لماذا المسارات مهمة؟</span>
                        <p class="page-inline-note-body">{{ $goal }}</p>
                    </div>
                    <div class="page-inline-note">
                        <span class="page-inline-note-label">ما الذي تفعله بعد الاختيار؟</span>
                        <p class="page-inline-note-body">{{ $nextStep }}</p>
                    </div>
                </div>
            </div>

            <div class="path-guide-card reveal-right d-2">
                <div class="path-guide-glow" aria-hidden="true"></div>

                <div class="path-guide-step">
                    <span class="path-guide-step-index">01</span>
                    <div>
                        <h2 class="path-guide-step-title">انظر إلى مرحلتك الحالية</h2>
                        <p class="path-guide-step-body">هل تبدأ من فكرة؟ هل تبيع خدمة؟ هل لديك مشروع قائم؟ نقطة البداية الصحيحة تختلف حسب وضعك الحالي.</p>
                    </div>
                </div>

                <div class="path-guide-step">
                    <span class="path-guide-step-index">02</span>
                    <div>
                        <h2 class="path-guide-step-title">حدّد أقرب مشكلة أمامك</h2>
                        <p class="path-guide-step-body">اختر المسار الذي يمنحك أول نتيجة تحتاجها الآن، لا المسار الذي يبدو شاملاً فقط.</p>
                    </div>
                </div>

                <div class="path-guide-step">
                    <span class="path-guide-step-index">03</span>
                    <div>
                        <h2 class="path-guide-step-title">تحرّك بعدها مباشرة</h2>
                        <p class="path-guide-step-body">المسار ليس تصنيفًا نظريًا، بل بداية تقودك للأداة والمرحلة والخطوة التالية داخل مشروعك.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">مبادئ الاختيار</p>
            <h2 class="heading-lg mb-4">ابدأ من واقعك، لا من <span class="text-gradient">التشتت</span></h2>
            <p class="text-body">المسارات تقلل الحيرة وتوجهك إلى الخطوة التي تعطي مشروعك أكبر قيمة الآن.</p>
        </div>

        <div class="three-col">
            @foreach ($pathPrinciples as $principle)
                <article class="page-feature-card {{ ['page-feature-primary', 'page-feature-teal', 'page-feature-gold'][$loop->index % 3] }} reveal d-{{ min($loop->iteration, 5) }}">
                    <div class="page-feature-bar" aria-hidden="true"></div>
                    <span class="page-feature-index">0{{ $loop->iteration }}</span>
                    <h2 class="page-feature-title">{{ $principle['title'] }}</h2>
                    <p class="page-feature-body">{{ $principle['body'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="section-lg bg-3">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-teal">المسارات الخمسة</p>
            <h2 class="heading-lg mb-4">اختر الطريق الذي يشبه <span class="text-gradient-teal">واقعك الآن</span></h2>
            <p class="text-body">كل مسار يشرح لمن يناسب، ما المشكلة التي يبدأ منها، وما النتيجة التي يساعدك على الوصول إليها.</p>
        </div>

        <div class="path-journey-grid">
            @foreach ($pathCards as $path)
                <article class="path-card path-journey-card {{ $pathTones[$loop->index % count($pathTones)] }} reveal d-{{ min($loop->iteration, 5) }}">
                    <div class="path-journey-icon" aria-hidden="true">{{ ['01', '02', '03', '04', '05'][$loop->index] }}</div>

                    <div class="path-journey-header">
                        <span class="path-card-tag">{{ $path['audience'] }}</span>
                        <h3 class="path-card-title">{{ $path['name'] }}</h3>
                    </div>

                    <div class="path-journey-panel">
                        <p class="path-journey-panel-label">متى يكون مناسباً؟</p>
                        <p class="path-journey-panel-body">{{ $path['problem'] }}</p>
                    </div>

                    <div class="path-journey-copy">
                        <p class="path-journey-panel-label">ماذا يقدّم لك؟</p>
                        <p class="path-card-body">{{ $path['promise'] }}</p>
                    </div>

                    <div class="path-card-cta">
                        خطوة بداية أوضح
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                            <path d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="section-lg">
    <div class="site-container">
        <div class="cta-section reveal">
            <div class="cta-section-grid"></div>
            <div class="cta-section-glow"></div>
            <div class="cta-section-content">
                <div class="section-badge internal-cta-badge mb-5">
                    <span class="section-badge-text internal-cta-badge-text">بعد اختيار المسار</span>
                </div>
                <h2 class="cta-headline">بعد تحديد مسارك، تبدأ <span class="text-gradient-light">الرحلة التنفيذية</span></h2>
                <p class="cta-body">ستنتقل من المدخل المناسب إلى المرحلة ثم الأداة ثم مخرج واضح قابل للاستخدام داخل مشروعك.</p>
                <div class="cta-actions">
                    <a href="{{ route('tools.index') }}" class="btn btn-primary btn-xl">استعرض الأدوات</a>
                    <a href="{{ route('projects.index') }}" class="btn btn-ghost btn-xl">اذهب إلى المشاريع</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
