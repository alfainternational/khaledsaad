@extends('layouts.public')

@section('title', $page['title'].' | خالد سعد')
@section('description', $page['intro'])

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner">
                <p class="eyebrow">{{ $page['eyebrow'] }}</p>
                <h1>{{ $page['title'] }}</h1>
                <p class="page-hero__lead">{{ $page['intro'] }}</p>
                <p class="legal-updated">آخر تحديث: {{ $page['updated'] }}</p>
            </div>
        </section>

        <section class="section legal-section">
            <div class="container legal-body">
                @foreach ($page['sections'] as $section)
                    <article class="legal-block">
                        <h2>{{ $section['title'] }}</h2>
                        @foreach ($section['paragraphs'] as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach

                        @if (! empty($section['points']))
                            <ul class="check-list">
                                @foreach ($section['points'] as $point)
                                    <li><span>✓</span> {{ $point }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach

                <div class="cta-band">
                    <p>عندك سؤال عن أي بند هنا؟ اسأل مباشرة ونجاوبك بلغة واضحة.</p>
                    <a class="button button--primary" href="{{ $brand['contact']['whatsapp'] }}" target="_blank" rel="noopener noreferrer">تواصل عبر واتساب</a>
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer')
@endsection
