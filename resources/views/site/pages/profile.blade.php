@extends('layouts.public')
@section('layout', 'marketing')

@section('title', 'السيرة المهنية لخالد سعد | إدارة التسويق والتسويق التعليمي')
@section('description', $brand['professional_headline'].' — خبرة تتجاوز عشر سنوات في التسويق الرقمي والحملات والمحتوى وتحليل الأداء.')

@section('content')
    @include('partials.site-header')

    <main id="main-content" class="profile-page">
        <section class="profile-hero">
            <div class="container profile-hero__grid">
                <div>
                    <p class="eyebrow">السيرة المهنية</p>
                    <h1>{{ $brand['name'] }}</h1>
                    <p class="profile-hero__headline">{{ $brand['professional_headline'] }}</p>
                    <p class="profile-hero__location">{{ $brand['location'] }} · {{ $brand['experience_years'] }}</p>
                    <div class="profile-hero__actions">
                        <a class="button button--primary" href="{{ route('profile.pdf') }}">تنزيل السيرة PDF</a>
                        <a class="button button--ghost" href="{{ $brand['contact']['linkedin'] }}" target="_blank" rel="noopener noreferrer">LinkedIn</a>
                        <a class="button button--ghost" href="{{ $brand['contact']['whatsapp'] }}" target="_blank" rel="noopener noreferrer">تواصل عبر WhatsApp</a>
                    </div>
                </div>
                <div class="profile-hero__mark" aria-hidden="true">
                    <img src="{{ asset('assets/brand/khaled-saad-approved.png') }}" alt="">
                </div>
            </div>
        </section>

        <div class="container profile-layout">
            <aside class="profile-summary" aria-label="ملخص السيرة">
                <section>
                    <h2>بيانات التواصل</h2>
                    <a dir="ltr" href="tel:{{ $brand['contact']['phone'] }}">{{ $brand['contact']['phone_display'] }}</a>
                    <a href="{{ $brand['contact']['linkedin'] }}" target="_blank" rel="noopener noreferrer">LinkedIn</a>
                    <a href="{{ $brand['contact']['x'] }}" target="_blank" rel="noopener noreferrer">X / Twitter</a>
                </section>
                <section>
                    <h2>الخدمات المهنية</h2>
                    <ul class="profile-tags">
                        @foreach ($brand['professional_services'] as $service)
                            <li>{{ $service }}</li>
                        @endforeach
                    </ul>
                </section>
                <section>
                    <h2>الشهادات والتعلّم</h2>
                    <strong class="profile-summary__number">{{ count($brand['credentials']) }}</strong>
                    <p>شهادة واعتماد مهني موثّق أعرض تفاصيلها كاملة في هذه الصفحة.</p>
                </section>
            </aside>

            <div class="profile-main">
                <section class="profile-section" aria-labelledby="profile-about">
                    <p class="eyebrow">نبذة</p>
                    <h2 id="profile-about">خبرة تربط الاستراتيجية بالتنفيذ والقياس</h2>
                    @foreach ($brand['about'] as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </section>

                <section class="profile-section" aria-labelledby="profile-experience">
                    <p class="eyebrow">المسار المهني</p>
                    <h2 id="profile-experience">الخبرات المهنية</h2>
                    <div class="profile-timeline">
                        @foreach ($brand['experience'] as $job)
                            <article class="profile-job">
                                <header>
                                    <div>
                                        <h3>{{ $job['role'] }}</h3>
                                        <strong>{{ $job['company'] }}</strong>
                                    </div>
                                    <div class="profile-job__meta">
                                        <span>{{ $job['period'] }}</span>
                                        <span>{{ $job['location'] }}</span>
                                    </div>
                                </header>
                                @if (! empty($job['responsibilities']))
                                    <ul>
                                        @foreach ($job['responsibilities'] as $responsibility)
                                            <li>{{ $responsibility }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>

                <div class="profile-columns">
                    <section class="profile-section" aria-labelledby="profile-education">
                        <p class="eyebrow">التعليم</p>
                        <h2 id="profile-education">المؤهلات الأكاديمية</h2>
                        @foreach ($brand['education'] as $education)
                            <article class="profile-credential">
                                <h3>{{ $education['degree'] }}</h3>
                                <strong>{{ $education['institution'] }}</strong>
                                <span>{{ $education['period'] }}</span>
                            </article>
                        @endforeach
                    </section>
                    <section class="profile-section" aria-labelledby="profile-skills">
                        <p class="eyebrow">القدرات</p>
                        <h2 id="profile-skills">المهارات</h2>
                        <ul class="profile-tags profile-tags--wide">
                            @foreach ($brand['skills'] as $skill)
                                <li>{{ $skill }}</li>
                            @endforeach
                        </ul>
                    </section>
                </div>

                <section class="profile-section" aria-labelledby="profile-credentials">
                    <p class="eyebrow">التعلّم المستمر</p>
                    <h2 id="profile-credentials">الشهادات والتراخيص</h2>
                    <div class="profile-credentials">
                        @foreach ($brand['credentials'] as $credential)
                            <article class="profile-credential-card">
                                <span class="profile-credential-card__icon" aria-hidden="true">✓</span>
                                <div>
                                    <h3>{{ $credential['name'] }}</h3>
                                    @if (! empty($credential['issuer']) || ! empty($credential['issued']))
                                        <p>
                                            @if (! empty($credential['issuer']))<strong>{{ $credential['issuer'] }}</strong>@endif
                                            @if (! empty($credential['issuer']) && ! empty($credential['issued']))<span aria-hidden="true"> · </span>@endif
                                            @if (! empty($credential['issued']))<span>{{ $credential['issued'] }}</span>@endif
                                        </p>
                                    @endif
                                    @if (! empty($credential['credential_id']))
                                        <small>مُعرّف الاعتماد: <bdi>{{ $credential['credential_id'] }}</bdi></small>
                                    @endif
                                    @if (! empty($credential['url']))
                                        <a class="profile-credential-card__link" href="{{ $credential['url'] }}" target="_blank" rel="noopener noreferrer">عرض الاعتماد <span aria-hidden="true">↗</span></a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="profile-section" aria-labelledby="profile-knowledge">
                    <p class="eyebrow">المحتوى المهني</p>
                    <h2 id="profile-knowledge">المعرفة التي أشاركها</h2>
                    <div class="public-page-grid">
                        @foreach ($brand['knowledge'] as $item)
                            <article class="public-page-card">
                                <h3>{{ $item['title'] }}</h3>
                                <p>{{ $item['description'] }}</p>
                            </article>
                        @endforeach
                    </div>
                    <a class="text-link" href="{{ route('content.index') }}">استعرض مكتبة المقالات والدروس <span aria-hidden="true">←</span></a>
                </section>
            </div>
        </div>
    </main>

    @include('partials.site-footer')
@endsection
