@extends('layouts.public')
@section('layout', 'marketing')

@section('title', $label.' | خالد سعد')
@section('description', $description)

@section('content')
    @include('partials.site-header')

    <main id="main-content" class="public-page public-page--{{ $pageKey }}">
        <section class="public-page-hero">
            <div class="container public-page-hero__inner">
                <nav class="crumbs" aria-label="مسار الصفحة"><a href="{{ route('home') }}">الرئيسية</a><span>←</span><b>{{ $label }}</b></nav>
                <p class="eyebrow">{{ $label }}</p>
                <h1>{{ $title }}</h1>
                <p>{{ $description }}</p>
            </div>
        </section>

        <div class="container public-page-body">
            @foreach ($collections as $collection)
                <section class="public-page-section">
                    <h2>{{ $collection['title'] }}</h2>

                    @if ($collection['faq'] ?? false)
                        <div class="public-faq-list">
                            @foreach ($collection['items'] as $item)
                                <details>
                                    <summary>{{ $item['question'] }}</summary>
                                    <p>{{ $item['answer'] }}</p>
                                </details>
                            @endforeach
                        </div>
                    @elseif ($collection['sample'] ?? false)
                        <div class="public-sample-report">
                            <div class="public-sample-report__score"><strong>64<small>/100</small></strong><span>درجة جاهزية توضيحية</span></div>
                            <div class="public-page-grid">
                                @foreach ($collection['items'] as $item)
                                    <article class="public-page-card"><h3>{{ $item['title'] }}</h3><p>{{ $item['description'] }}</p></article>
                                @endforeach
                            </div>
                            <p class="public-sample-report__next"><span>الخطوة التالية</span><strong>اكتب سبب الشراء منك في صفحتك الأولى، وابنِ صفحة الشراء، قبل زيادة ميزانية الإعلان.</strong></p>
                        </div>
                    @else
                        <div class="public-page-grid">
                            @foreach ($collection['items'] as $item)
                                <article class="public-page-card">
                                    @if (isset($item['step']) || isset($item['number']))
                                        <span class="public-page-card__number">{{ $item['step'] ?? $item['number'] }}</span>
                                    @endif
                                    <h3>{{ $item['title'] }}</h3>
                                    <p>{{ $item['description'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach

            @if ($pageKey === 'knowledge')
                <section class="public-page-section">
                    <div class="public-page-section__head"><h2>أحدث المواد المنشورة</h2><a class="text-link" href="{{ route('content.index') }}">كل المكتبة <span aria-hidden="true">←</span></a></div>
                    @if ($latestContent->isEmpty())
                        <p>تُضاف المقالات والدروس المنشورة هنا تلقائيًا من مكتبة المحتوى.</p>
                    @else
                        <div class="public-page-grid">
                            @foreach ($latestContent as $content)
                                <a class="public-page-card" href="{{ route('content.show', $content) }}"><span class="public-page-card__number">{{ ['article' => 'مقال', 'lesson' => 'درس', 'lecture' => 'محاضرة', 'course' => 'دورة'][$content->type] ?? 'محتوى' }}</span><h3>{{ $content->title }}</h3><p>{{ $content->excerpt }}</p></a>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            @if ($pageKey === 'faq')
                <section class="public-contact-band">
                    <div><p class="eyebrow">تواصل</p><h2>هل تحتاج إلى مناقشة حالتك مباشرة؟</h2><p>{{ $brand['location'] }}</p></div>
                    <div><a class="button button--primary" href="{{ $brand['contact']['whatsapp'] }}" target="_blank" rel="noopener noreferrer">WhatsApp</a><a class="button button--ghost" dir="ltr" href="tel:{{ $brand['contact']['phone'] }}">{{ $brand['contact']['phone_display'] }}</a></div>
                </section>
            @endif
        </div>
    </main>

    @include('partials.site-footer')
@endsection
