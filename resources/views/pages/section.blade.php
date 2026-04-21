@extends('layouts.marketing')

@section('content')
@php
    $highlightTones = ['page-feature-primary', 'page-feature-teal', 'page-feature-gold'];
@endphp

<section class="section-lg internal-page-hero">
    <div class="site-container">
        <div class="two-col-wide internal-page-layout">
            <div class="reveal-left">
                <div class="section-badge">
                    <span class="section-dot"></span>
                    <span class="section-badge-text">{{ $eyebrow }}</span>
                </div>

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
                        <span class="page-inline-note-label">الهدف</span>
                        <p class="page-inline-note-body">{{ $goal }}</p>
                    </div>
                    <div class="page-inline-note">
                        <span class="page-inline-note-label">الخطوة التالية</span>
                        <p class="page-inline-note-body">{{ $nextStep }}</p>
                    </div>
                </div>
            </div>

            <div class="page-summary-card reveal-right d-2">
                <div class="page-summary-glow" aria-hidden="true"></div>

                <div class="page-summary-item">
                    <p class="page-summary-label">ماذا ستنجز هنا؟</p>
                    <p class="page-summary-body">{{ $goal }}</p>
                </div>

                <div class="page-summary-divider"></div>

                <div class="page-summary-item">
                    <p class="page-summary-label">كيف تبدأ بشكل صحيح؟</p>
                    <p class="page-summary-body">{{ $nextStep }}</p>
                </div>

                <div class="page-summary-list">
                    @foreach ($highlights as $highlight)
                        <div class="page-summary-list-item">
                            <span class="page-summary-list-dot" aria-hidden="true"></span>
                            <span>{{ $highlight['title'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-band bg-2">
    <div class="site-container">
        <div class="section-header reveal">
            <p class="text-eyebrow mb-3 text-p">أهم ما ستجده</p>
            <h2 class="heading-lg mb-4">تجربة بخطوات واضحة تقودك إلى <span class="text-gradient">قرار أوضح</span></h2>
            <p class="text-body">كل صفحة هنا تقلل الحيرة وتقربك من قرار أو مخرج عملي يمكنك البناء عليه.</p>
        </div>

        <div class="three-col">
            @foreach ($highlights as $highlight)
                <article class="page-feature-card {{ $highlightTones[$loop->index % count($highlightTones)] }} reveal d-{{ min($loop->iteration, 5) }}">
                    <div class="page-feature-bar" aria-hidden="true"></div>
                    <span class="page-feature-index">0{{ $loop->iteration }}</span>
                    <h2 class="page-feature-title">{{ $highlight['title'] }}</h2>
                    <p class="page-feature-body">{{ $highlight['body'] }}</p>
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
                <div class="section-badge mb-5 internal-cta-badge">
                    <span class="section-badge-text internal-cta-badge-text">الانتقال التالي</span>
                </div>
                <h2 class="cta-headline">عندما تتضح الصفحة، <span class="text-gradient-light">تتضح الخطوة التالية</span></h2>
                <p class="cta-body">{{ $nextStep }}</p>
                <div class="cta-actions">
                    @foreach ($actions as $action)
                        <a href="{{ $action['href'] }}" class="btn {{ $loop->first ? 'btn-primary' : 'btn-ghost' }} btn-xl">
                            {{ $action['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
