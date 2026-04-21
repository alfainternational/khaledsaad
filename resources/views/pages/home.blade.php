@extends('layouts.marketing')

@section('content')
@php
    $heroAvatarToneClasses = [
        'hero-avatar-tone-primary',
        'hero-avatar-tone-teal',
        'hero-avatar-tone-gold',
        'hero-avatar-tone-rose',
        'hero-avatar-tone-violet',
    ];
    $heroAvatarDepthClasses = [
        'hero-avatar-depth-1',
        'hero-avatar-depth-2',
        'hero-avatar-depth-3',
        'hero-avatar-depth-4',
        'hero-avatar-depth-5',
    ];
    $metricToneClasses = [
        'metric-top-bar-primary',
        'metric-top-bar-teal',
        'metric-top-bar-gold',
    ];
    $benefitToneClasses = [
        'benefit-tone-primary',
        'benefit-tone-teal',
        'benefit-tone-gold',
        'benefit-tone-rose',
    ];
    $studioProgressItems = [
        ['label' => 'تحليل السوق', 'percentage' => 100, 'pctClass' => 'studio-progress-pct-primary', 'fillClass' => 'studio-progress-fill-primary studio-progress-fill-100'],
        ['label' => 'بناء العرض', 'percentage' => 75, 'pctClass' => 'studio-progress-pct-teal', 'fillClass' => 'studio-progress-fill-teal studio-progress-fill-75'],
    ];
    $pathToneClasses = [
        'path-card-tone-primary',
        'path-card-tone-teal',
        'path-card-tone-gold',
        'path-card-tone-rose',
    ];
@endphp

{{-- ═══ HERO SECTION ═══ --}}
<section class="hero-section">
    <div class="site-container">
        <div class="hero-layout">
            {{-- Text Content --}}
            <div class="hero-text reveal-left">
                <x-marketing.section-badge text="منهجية تشغيل واضحة" />

                <h1 class="hero-headline">
                    @php $headline = explode('،', $hero['headline']); @endphp
                    {{ $headline[0] }}<br>
                    <span class="text-gradient">{{ $headline[1] ?? '' }}</span>
                </h1>

                <p class="hero-body">
                    {{ $hero['body'] }}
                </p>

                <div class="hero-ctas">
                    <a href="{{ $hero['primaryCta']['href'] }}" class="btn btn-primary btn-lg">
                        {{ $hero['primaryCta']['label'] }}
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                    <a href="{{ $hero['secondaryCta']['href'] }}" class="btn btn-secondary btn-lg">
                        {{ $hero['secondaryCta']['label'] }}
                    </a>
                </div>

                <div class="hero-trust">
                    <div class="hero-avatars">
                        @for($i=0; $i<5; $i++)
                            <div class="hero-avatar {{ $heroAvatarToneClasses[$i] }} {{ $heroAvatarDepthClasses[$i] }}">
                                {{ mb_substr($hero['primaryCta']['label'], $i, 1) }}
                            </div>
                        @endfor
                    </div>
                    <span class="hero-trust-text">+2,400 رائد أعمال يثقون بالمنهجية</span>
                    <div class="hero-divider"></div>
                    <div class="hero-trust-badge">
                        <svg width="14" height="14" fill="none" stroke="var(--teal)" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>خطوات متتابعة</span>
                    </div>
                </div>
            </div>

            {{-- Visual Content --}}
            <div class="hero-visual reveal-right d-2">
                <div class="hero-img-glow"></div>
                <div class="hero-img-wrap">
                    <img src="{{ asset('images/hero.png') }}" alt="المنصة الاستراتيجية" class="hero-img">
                </div>

                <div class="hero-chip hero-chip-1 hero-chip-floating-primary">
                    <div>
                        <p class="hero-chip-label">مخرجات ذكية</p>
                        <p class="hero-chip-value">تجهيز النتيجة التالية تلقائياً</p>
                    </div>
                </div>

                <div class="hero-chip hero-chip-2 hero-chip-floating-secondary">
                    <div>
                        <p class="hero-chip-label">رضا المستخدمين</p>
                        <p class="hero-chip-value">4.9 من 5</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══ TRUST STRIP ═══ --}}
<div class="trust-strip">
    <div class="site-container">
        <div class="trust-strip-inner">
            @foreach(['المملكة العربية السعودية', 'الإمارات', 'مصر', 'الأردن', 'الكويت', 'قطر'] as $index => $country)
                <span class="trust-strip-item reveal d-{{ $index+1 }}">{{ $country }}</span>
            @endforeach
        </div>
    </div>
</div>

{{-- ═══ METRICS SECTION ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">مؤشرات الأداء الاستراتيجي</p>
            <h2 class="heading-lg mb-4">نتائج ملموسة تحققت عبر <span class="text-gradient">نمو مدروس</span></h2>
        </div>

        <div class="three-col">
            @foreach($metrics as $i => $metric)
                <x-marketing.metric-card
                    :value="$metric['value']"
                    :label="$metric['label']"
                    :tone-class="$metricToneClasses[$i % 3]"
                    :class="'reveal d-'.($i+1)"
                />
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ PHILOSOPHY & BENEFITS ═══ --}}
<section class="section-band bg-2">
    <div class="site-container">
        <div class="two-col-wide">
            <div class="reveal-left">
                <p class="text-eyebrow mb-3 text-p">الهندسة الاستراتيجية</p>
                <h2 class="heading-lg mb-5">الربط المنطقي بين <span class="text-gradient">الرؤية والنمو</span></h2>
                <p class="text-body-lg mb-6">
                    نقدم لك نظاماً عملياً يربط بين أهداف مشروعك وخطوات التنفيذ والنمو.
                </p>
                <a href="{{ route('paths.index') }}" class="btn btn-primary btn-lg">
                    استكشف المنهجية
                </a>
            </div>

            <div class="flex-col gap-4 reveal-right d-2">
                @foreach($benefits as $i => $benefit)
                    <x-marketing.benefit-card
                        :title="$benefit['title']"
                        :body="$benefit['body']"
                        :tone-class="$benefitToneClasses[$i % 4]"
                    />
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ═══ AI STUDIO PREVIEW ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="two-col">
            <div class="studio-window reveal-left">
                <div class="studio-titlebar">
                    <div class="window-dot window-dot-red"></div>
                    <div class="window-dot window-dot-yellow"></div>
                    <div class="window-dot window-dot-green"></div>
                    <div class="flex-1"></div>
                    <div class="section-dot"></div>
                </div>
                <div class="studio-body">
                    <div class="studio-message">
                        <div class="studio-msg-avatar studio-msg-avatar-user">أنت</div>
                        <div>
                            <p class="studio-msg-role">رائد الأعمال</p>
                            <p class="studio-msg-text">صياغة العرض التسويقي لخدمات التدريب الاستشاري.</p>
                        </div>
                    </div>
                    <div class="studio-ai-box">
                        <div class="studio-ai-header">
                            <div class="studio-msg-avatar studio-msg-avatar-ai">ذكاء</div>
                            <p class="section-badge-text section-badge-text-accent">الاستوديو الذكي</p>
                        </div>
                        <p class="studio-msg-text">بناءً على بيانات مشروعك، هذه مسودة مقترحة يمكنك تعديلها مباشرة.</p>
                        <div class="studio-progress">
                            @foreach($studioProgressItems as $progressItem)
                                <div class="studio-progress-item">
                                    <div class="studio-progress-label-row">
                                        <span class="studio-progress-label">{{ $progressItem['label'] }}</span>
                                        <span class="studio-progress-pct {{ $progressItem['pctClass'] }}">{{ $progressItem['percentage'] }}%</span>
                                    </div>
                                    <div class="studio-progress-track">
                                        <div class="studio-progress-fill {{ $progressItem['fillClass'] }}"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="reveal-right d-2">
                <p class="text-eyebrow mb-3 text-teal">التحليل المتقدم</p>
                <h2 class="heading-md mb-4">النظام الآلي <span class="text-gradient-teal">لاستنباط المخرجات</span></h2>
                <p class="text-body mb-6">
                    نقوم بمعالجة مخرجات المرحلة السابقة لاستنتاج الخطوة التالية بدقة استثنائية، مما يضمن تدفقاً منطقياً لا يتطلب إعادة إدخال البيانات.
                </p>
                <div class="two-col-cards">
                    @foreach(['توفير الوقت 80%', 'دقة التحليل', 'تسلسل منطقي', 'توصيات فورية'] as $index => $feat)
                        <div class="card card-sm reveal d-{{ $index+1 }}">
                            <div class="flex items-center gap-3">
                                <svg width="16" height="16" fill="none" stroke="var(--teal)" viewBox="0 0 24 24" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
                                <span class="text-caption text-caption-strong">{{ $feat }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══ AUDIENCE PATHS ═══ --}}
<section class="section-lg bg-3">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">خرائط الطريق</p>
            <h2 class="heading-lg mb-4">حدد مسارك <span class="text-gradient">نحو التوسع</span></h2>
            <p class="text-body">اختر المنهجية التي تتوافق مع وضع مشروعك الحالي لبدء الهندسة الاستراتيجية.</p>
        </div>

        <div class="two-col-cards">
            @foreach($audiences as $i => $audience)
                <x-marketing.path-card
                    :name="$audience['name']"
                    :summary="$audience['summary']"
                    :href="$audience['href']"
                    :tone-class="$pathToneClasses[$i % 4]"
                    :index="$i"
                    :class="'reveal d-'.($i+1)"
                />
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ FINAL CTA ═══ --}}
<section class="section-lg">
    <div class="site-container">
        <div class="cta-section reveal">
            <div class="cta-section-grid"></div>
            <div class="cta-section-glow"></div>
            <div class="cta-section-content">
                <div class="section-badge internal-cta-badge mb-5">
                    <span class="section-badge-text internal-cta-badge-text">ابدأ الآن</span>
                </div>
                <h2 class="cta-headline">هل أنت مستعد لبناء <span class="text-gradient-light">أصولك الاستراتيجية؟</span></h2>
                <p class="cta-body">
                    ابدأ بخطوات واضحة تساعدك على ترتيب التسويق وبناء نتائج قابلة للتنفيذ.
                </p>
                <div class="cta-actions">
                    <a href="{{ route('paths.index') }}" class="btn btn-primary btn-xl">ابدأ رحلتك مجاناً</a>
                    <a href="{{ route('tools.index') }}" class="btn btn-ghost btn-xl btn-inverse">شاهد كيف يعمل</a>
                </div>
                <p class="cta-note">لا يلزم بطاقة ائتمانية · مجاني للأبد في الخطة الأساسية</p>
            </div>
        </div>
    </div>
</section>
@endsection
